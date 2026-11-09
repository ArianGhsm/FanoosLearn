#!/usr/bin/env python3
"""Single-pass, multi-query page ranker over one exact approved reference PDF.

Policy (docs/ops/PDF_REFERENCE_SOURCE_POLICY_20261010.md): each page is read
alone through scripts/references/verified_reference_pdf.py, tokenised in memory
and discarded. Only numeric per-page counts of *query* terms are retained
(page id -> term id -> int). No page text, token list, index or cache is kept
or written. A second step re-reads only the top-k pages per question to extract
a short (<=30 word) candidate window for adjudication.
"""
from __future__ import annotations

import collections
import math
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / "scripts/references"))

TOKEN = re.compile(r"[a-z0-9]+(?:\.[0-9]+)?")
STOP = set("the a an of and or in on to for with by is are be as at from that this which".split())


def norm(text: str) -> str:
    text = text.lower().replace("’", "'").replace("ﬁ", "fi").replace("ﬂ", "fl")
    text = re.sub(r"-\s*\n\s*", "", text)          # rejoin hyphenated line breaks
    return re.sub(r"\s+", " ", text)


def stem(word: str) -> str:
    """Tiny conservative English stemmer so plural/adjectival forms match."""
    for suf in ("ations", "ation", "ings", "ing", "ies", "es", "s"):
        if len(word) > len(suf) + 3 and word.endswith(suf):
            return word[: -len(suf)] + ("y" if suf == "ies" else "")
    return word


def tokens(text: str) -> list[str]:
    return [stem(t) for t in TOKEN.findall(norm(text)) if t not in STOP]


@dataclass
class Query:
    key: str
    groups: list[tuple[list[str], float]]          # (stemmed token phrase, weight)
    negative: list[list[str]] = field(default_factory=list)

    @classmethod
    def build(cls, key: str, terms: list, negative: list | None = None) -> "Query":
        groups = []
        for term in terms:
            text, weight = (term, 1.0) if isinstance(term, str) else (term[0], float(term[1]))
            toks = tokens(text)
            if toks:
                groups.append((toks, weight))
        neg = [tokens(t) for t in (negative or []) if tokens(t)]
        return cls(key, groups, neg)


def is_noise_page(toks: list[str], text: str) -> bool:
    """Index, contents, reference lists: high citation density, few prose tokens."""
    if len(toks) < 40:
        return True
    lines = [l for l in text.splitlines() if l.strip()]
    cited = sum(1 for l in lines if re.search(r"\bet al\b|\(\d+\):\s*\d+|\b(19|20)\d\d[;.]", l))
    numbered = sum(1 for l in lines if re.search(r",\s*\d+(–\d+)?[a-z]?\s*$", l))
    return cited > 0.3 * len(lines) or numbered > 0.5 * len(lines)


def count_page(text: str, phrases: dict[tuple, int]) -> tuple[dict[int, int], int, bool]:
    """Return {phrase_id: count}, page token length, noise flag. Text is not retained."""
    toks = tokens(text)
    noise = is_noise_page(toks, text)
    joined = " " + " ".join(toks) + " "
    unigram = collections.Counter(toks)
    counts = {}
    for phrase, pid in phrases.items():
        c = unigram.get(phrase[0], 0) if len(phrase) == 1 else joined.count(" " + " ".join(phrase) + " ")
        if c:
            counts[pid] = c
    return counts, len(toks), noise


def rank(queries: list[Query], page_counts: dict[int, dict[int, int]], page_len: dict[int, int],
         noise: set[int], phrases: dict[tuple, int], top: int = 5) -> dict[str, list[tuple[float, int]]]:
    """BM25 over the retained numeric counts, with phrase/coverage bonuses."""
    pages = [p for p in page_counts if p not in noise]
    n = max(1, len(pages))
    avg = sum(page_len[p] for p in pages) / n if pages else 1.0
    df = collections.Counter(pid for p in pages for pid in page_counts[p])
    out = {}
    for q in queries:
        scored = []
        for p in pages:
            pc = page_counts[p]
            s, covered, total_w = 0.0, 0.0, 0.0
            for toks, w in q.groups:
                pid = phrases[tuple(toks)]
                total_w += w
                tf = pc.get(pid, 0)
                if not tf:
                    continue
                idf = math.log(1 + (n - df[pid] + 0.5) / (df[pid] + 0.5))
                bonus = 1.6 if len(toks) > 1 else 1.0
                s += w * bonus * idf * tf * 2.2 / (tf + 1.2 * (0.25 + 0.75 * page_len[p] / avg))
                covered += w
            if not s:
                continue
            s *= (0.4 + 0.6 * covered / total_w)       # reward covering many concepts
            for neg in q.negative:
                if pc.get(phrases[tuple(neg)], 0):
                    s *= 0.85
            scored.append((round(s, 4), p))
        scored.sort(key=lambda x: (-x[0], x[1]))
        out[q.key] = scored[:top]
    return out


def best_window(text: str, q: Query, words: int = 30) -> tuple[str, int]:
    """Short window with the most distinct query groups; returned for adjudication only."""
    raw = norm(text).split(" ")
    st = [stem(re.sub(r"[^a-z0-9.]", "", w).strip(".")) for w in raw]
    best, best_i = -1, 0
    for i in range(0, max(1, len(raw) - words + 1), 3):
        win = " " + " ".join(st[i:i + words]) + " "
        hits = sum(1 for toks, _ in q.groups if " " + " ".join(toks) + " " in win)
        if hits > best:
            best, best_i = hits, i
    return " ".join(raw[best_i:best_i + words]), max(0, best)


def chapter_at(runs: list, page: int):
    for chapter, first, last in runs:
        if first <= page <= last:
            return chapter
    return None


def scan_edition(pdf, queries: list[Query], pages: range | None = None, top: int = 5):
    """One pass over `pages` (default whole PDF). Returns ranking and stats."""
    import time
    phrases: dict[tuple, int] = {}
    for q in queries:
        for toks, _ in q.groups:
            phrases.setdefault(tuple(toks), len(phrases))
        for neg in q.negative:
            phrases.setdefault(tuple(neg), len(phrases))
    page_counts, page_len, noise = {}, {}, set()
    t0 = time.time()
    for p in (pages or range(1, pdf.page_count + 1)):
        text = pdf.page_text(p)
        page_counts[p], page_len[p], is_noise = count_page(text, phrases)
        del text
        if is_noise:
            noise.add(p)
    elapsed = time.time() - t0
    ranking = rank(queries, page_counts, page_len, noise, phrases, top)
    return ranking, {"pages_scanned": len(page_counts), "noise_pages": len(noise),
                     "scan_seconds": round(elapsed, 2), "terms": len(phrases)}


def regex_windows(text: str, pattern: str, width: int = 160, limit: int = 3) -> list[str]:
    """Short windows around regex hits on one page (adjudication only, not persisted)."""
    flat = norm(text)
    out = []
    for m in re.finditer(pattern, flat):
        out.append(flat[max(0, m.start() - width // 2): m.end() + width // 2])
        if len(out) >= limit:
            break
    return out
