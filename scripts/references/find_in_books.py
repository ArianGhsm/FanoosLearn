#!/usr/bin/env python3
"""Find where in the reference books a question's content is.

For each query -- a question key, the editions to look in, and the terms to
look for (English, as the books are written) -- prints the pages that match
best, with the chapter each page belongs to and the lines that matched. A
person (or the model doing the classification) reads those lines and decides;
this tool only finds and shows, it never decides.

Pages come from .local/references/<edition>.txt and chapters from
data/bank/reference-chapter-pages.json (PROJECT_PRINCIPLES decision 6).
Index, contents and bibliography pages are skipped: they name everything and
explain nothing.

    python scripts/references/find_in_books.py queries.json [--top 3]

queries.json: [{"key": "residency-1403-1-041", "editions": ["neville-oral-pathology@4e"],
                "terms": ["periapical cyst", "mucous cells", "hyaline bodies", "Rushton"]}]
A term of several words counts most when the words appear together.
"""
from __future__ import annotations

import argparse
import collections
import json
import math
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from apply_classification import flat as evidence_flat  # noqa: E402  (the same check the quotes must pass)
CHAPTERS = REPO / 'data' / 'bank' / 'reference-chapter-pages.json'
PAGE = re.compile(r'^=== PAGE (\d+) ===$', re.M)
TOKEN = re.compile(r'[a-z0-9]+')


def norm(text: str) -> str:
    text = text.lower().replace('’', "'").replace('ﬁ', 'fi').replace('ﬂ', 'fl').replace('¿', 'fi').replace('À', 'fl')
    return re.sub(r'-\s*\n\s*', '', text)


class Book:
    def __init__(self, path: Path, runs: list):
        raw = path.read_text(encoding='utf-8')
        marks = list(PAGE.finditer(raw))
        self.pages: list[tuple[int, str]] = []
        for m, nxt in zip(marks, marks[1:] + [None]):
            text = raw[m.end():nxt.start() if nxt else len(raw)]
            if not self.skipped(text):
                self.pages.append((int(m.group(1)), text))
        self.flat = [re.sub(r'\s+', ' ', norm(t)) for _, t in self.pages]
        self.tokens = [TOKEN.findall(f) for f in self.flat]
        self.df = collections.Counter(t for toks in self.tokens for t in set(toks))
        self.avg = sum(len(t) for t in self.tokens) / max(1, len(self.tokens))
        self.chapter_of = {}
        for chapter, first, last in runs:
            for p in range(first, last + 1):
                self.chapter_of[p] = chapter

    @staticmethod
    def skipped(text: str) -> bool:
        lines = [l.strip() for l in text.splitlines() if l.strip()]
        if len(lines) < 4:
            return True
        head = ' '.join(lines[:3]).lower()
        if re.match(r'(index|contents)\b', head):
            return True
        numbered = sum(1 for l in lines if re.search(r'(,\s*\d+(–\d+)?[a-z]?)$', l))
        cited = sum(1 for l in lines if re.search(r'\bet al\b|\(\d+\):\s*\d+|\b(19|20)\d\d[;.]', l))
        return numbered > 0.5 * len(lines) or cited > 0.3 * len(lines)

    def idf(self, token: str) -> float:
        n = len(self.pages)
        return math.log(1 + (n - self.df.get(token, 0) + 0.5) / (self.df.get(token, 0) + 0.5))

    def search(self, terms: list[str], top: int):
        parts = [TOKEN.findall(norm(t)) for t in terms]
        results = []
        for i, toks in enumerate(self.tokens):
            counts = collections.Counter(toks)
            length = len(toks) or 1
            score = 0.0
            hit_terms = 0
            for words in parts:
                if not words:
                    continue
                phrase = ' '.join(words)
                if len(words) > 1 and phrase in self.flat[i]:
                    score += 2.5 * sum(self.idf(w) for w in words)
                    hit_terms += 1
                    continue
                term_score = 0.0
                for w in words:
                    tf = counts.get(w, 0)
                    if tf:
                        term_score += self.idf(w) * tf * 2.2 / (tf + 1.2 * (0.25 + 0.75 * length / self.avg))
                if term_score:
                    hit_terms += 1 if all(counts.get(w) for w in words) else 0.5
                score += term_score / len(words)
            if score:
                results.append((score * (0.6 + 0.4 * hit_terms / max(1, len(parts))), i))
        results.sort(reverse=True)
        return results[:top]

    def snippet(self, i: int, terms: list[str], width: int = 230) -> str:
        flat = self.flat[i]
        best, best_hits = 0, -1
        words = [w for t in terms for w in TOKEN.findall(norm(t)) if len(w) > 2]
        for start in range(0, max(1, len(flat) - width), 60):
            window = flat[start:start + width]
            hits = sum(1 for w in words if w in window)
            if hits > best_hits:
                best, best_hits = start, hits
        return flat[best:best + width]

    def quote(self, i: int, terms: list[str], size: int = 10) -> str | None:
        """A run of whole words around the best match, already checked to be on
        the page the way apply_classification.py checks evidence -- ready to
        copy into a decision when it states the fact."""
        words = self.snippet(i, terms, 400).split()[1:-1]  # drop the cut words at the edges
        if len(words) < 4:
            return None
        wanted = [w for t in terms for w in TOKEN.findall(norm(t)) if len(w) > 2]
        best, best_hits = 0, -1
        for start in range(0, max(1, len(words) - size + 1)):
            window = ' '.join(words[start:start + size])
            hits = sum(1 for w in wanted if w in window)
            if hits > best_hits:
                best, best_hits = start, hits
        quote = ' '.join(words[best:best + size])
        page_text = evidence_flat(self.pages[i][1])
        return quote if len(quote.split()) >= 4 and evidence_flat(quote) in page_text else None


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('queries')
    parser.add_argument('--top', type=int, default=3)
    parser.add_argument('--local', default=str(REPO / '.local'))
    args = parser.parse_args()
    sys.stdout.reconfigure(encoding='utf-8')

    queries = json.loads(Path(args.queries).read_text(encoding='utf-8'))
    runs = json.loads(CHAPTERS.read_text(encoding='utf-8'))['editions']
    books: dict[str, Book] = {}
    for q in queries:
        print(f"## {q['key']}  {' | '.join(q['terms'])}")
        for edition in q['editions']:
            if edition not in books:
                path = Path(args.local) / 'references' / f'{edition}.txt'
                if not path.exists():
                    print(f'   {edition}: no text')
                    continue
                books[edition] = Book(path, runs.get(edition, {}).get('runs', []))
            book = books[edition]
            for score, i in book.search(q['terms'], args.top):
                page = book.pages[i][0]
                print(f"   {edition} p{page} ch{book.chapter_of.get(page)} {score:5.1f} | {book.snippet(i, q['terms'])}")
                quote = book.quote(i, q['terms'])
                if quote:
                    print(f'      quote: {quote}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
