#!/usr/bin/env python3
"""Search the exact approved PDF one page at a time, without text indexes.

Each query names an exam question, exact reference editions and search terms.
For each edition this script opens the current approved private PDF, asks
Poppler for one page at a time, and keeps only the best page numbers, short
snippets and quotes in memory. It writes no PDF text or search-index file and
does not decide a chapter assignment.

    python scripts/references/find_in_books.py queries.json [--top 3]
"""
from __future__ import annotations

import argparse
import collections
import heapq
import json
import math
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from apply_classification import flat as evidence_flat  # noqa: E402
from verified_reference_pdf import chapter_coverage, open_verified_reference  # noqa: E402

CHAPTERS = REPO / "data/bank/reference-chapter-pages.json"
TOKEN = re.compile(r"[^\W_]+", re.UNICODE)


def norm(text: str) -> str:
    text = text.lower().replace("’", "'").replace("ﬁ", "fi").replace("ﬂ", "fl").replace("¿", "fi").replace("À", "fl")
    text = text.replace("ي", "ی").replace("ى", "ی").replace("ك", "ک")
    return re.sub(r"-\s*\n\s*", "", text)


def skipped_page(text: str) -> bool:
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    if len(lines) < 4:
        return True
    head = " ".join(lines[:3]).lower()
    if re.match(r"(index|contents)\b", head):
        return True
    numbered = sum(1 for line in lines if re.search(r"(,\s*\d+(–\d+)?[a-z]?)$", line))
    cited = sum(1 for line in lines if re.search(r"\bet al\b|\(\d+\):\s*\d+|\b(19|20)\d\d[;.]", line))
    return numbered > 0.5 * len(lines) or cited > 0.3 * len(lines)


def page_score(text: str, terms: list[str], document_frequency: collections.Counter | None = None,
               document_count: int = 1, average_length: float | None = None) -> float:
    flat = re.sub(r"\s+", " ", norm(text)).strip()
    tokens = collections.Counter(TOKEN.findall(flat))
    groups = [TOKEN.findall(norm(term)) for term in terms]
    groups = [group for group in groups if group]
    if not groups:
        return 0.0
    document_frequency = document_frequency or collections.Counter()
    page_length = len(TOKEN.findall(flat)) or 1
    average_length = average_length or page_length

    def idf(word: str) -> float:
        frequency = document_frequency.get(word, 0)
        return math.log(1 + (document_count - frequency + 0.5) / (frequency + 0.5))

    score = 0.0
    covered = 0
    for group in groups:
        phrase = " ".join(group)
        phrase_count = flat.count(phrase) if len(group) > 1 else 0
        if phrase_count:
            score += 2.5 * sum(idf(word) for word in group) * min(phrase_count, 2)
            covered += 1
        else:
            term_score = 0.0
            counts = [tokens.get(word, 0) for word in group]
            for word, tf in zip(group, counts):
                if tf:
                    term_score += idf(word) * tf * 2.2 / (
                        tf + 1.2 * (0.25 + 0.75 * page_length / average_length))
            if term_score:
                covered += 1 if all(counts) else 0.5
            score += term_score / len(group)
    return score * (0.6 + 0.4 * covered / max(1, len(groups)))


def snippet(text: str, terms: list[str], width: int = 230) -> str:
    flat = re.sub(r"\s+", " ", norm(text)).strip()
    words = [word for term in terms for word in TOKEN.findall(norm(term)) if len(word) > 2]
    best_start, best_hits = 0, -1
    for start in range(0, max(1, len(flat) - width + 1), 60):
        window = flat[start:start + width]
        hits = sum(word in window for word in words)
        if hits > best_hits:
            best_start, best_hits = start, hits
    return flat[best_start:best_start + width]


def quote_page(text: str, terms: list[str], size: int = 10) -> str | None:
    """Return only a short candidate quote that is verifiably on this PDF page."""
    words = snippet(text, terms, 400).split()[1:-1]
    if len(words) < 4:
        return None
    wanted = [word for term in terms for word in TOKEN.findall(norm(term)) if len(word) > 2]
    best_start, best_hits = 0, -1
    for start in range(0, max(1, len(words) - size + 1)):
        window = " ".join(words[start:start + size])
        hits = sum(word in window for word in wanted)
        if hits > best_hits:
            best_start, best_hits = start, hits
    quote = " ".join(words[best_start:best_start + size])
    return quote if len(quote.split()) >= 4 and evidence_flat(quote) in evidence_flat(text) else None


def chapter_at(runs: list, page: int) -> str | None:
    for chapter, first, last in runs:
        if first <= page <= last:
            return None if chapter is None else str(chapter)
    return None


def search_edition(pdf, query_rows: list[dict], runs: list, top: int) -> dict[str, list[tuple]]:
    heaps: dict[str, list[tuple]] = {str(row["key"]): [] for row in query_rows}
    prepared = {
        str(row["key"]): [TOKEN.findall(norm(str(term))) for term in row.get("terms", []) if str(term).strip()]
        for row in query_rows
    }
    query_vocabulary = {word for groups in prepared.values() for group in groups for word in group}
    document_frequency: collections.Counter = collections.Counter()
    document_count = 0
    total_length = 0
    # First pass retains only query-token document frequencies and page lengths.
    # It never stores page text or per-page token lists.
    for page_number, page_text in pdf.iter_page_texts():
        if not skipped_page(page_text):
            tokens = TOKEN.findall(norm(page_text))
            document_count += 1
            total_length += len(tokens)
            document_frequency.update(set(tokens) & query_vocabulary)
            del tokens
        del page_text
    average_length = total_length / max(1, document_count)

    # Second pass keeps at most --top page numbers and short candidate quotes
    # per query; the current page text is discarded before the next page.
    for page_number, page_text in pdf.iter_page_texts():
        if not skipped_page(page_text):
            for query in query_rows:
                key = str(query["key"])
                terms = [str(term) for term in query.get("terms", []) if str(term).strip()]
                score = page_score(page_text, terms, document_frequency, document_count, average_length)
                if not score:
                    continue
                item = (score, -page_number, page_number,
                        chapter_at(runs, page_number), snippet(page_text, terms), quote_page(page_text, terms))
                heap = heaps[key]
                if len(heap) < top:
                    heapq.heappush(heap, item)
                elif item[:2] > heap[0][:2]:
                    heapq.heapreplace(heap, item)
        del page_text
    return {key: sorted(items, key=lambda item: (-item[0], item[2])) for key, items in heaps.items()}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("queries", help="temporary or durable JSON list of question keys, editions and search terms")
    parser.add_argument("--top", type=int, default=3)
    parser.add_argument("--storage-root", type=Path, default=Path("/srv/fanoos/shared/storage"))
    parser.add_argument("--mysql-defaults", type=Path, default=Path("/etc/fanoos/mysql-migrator.cnf"))
    parser.add_argument("--database", default="fanoos_prod")
    args = parser.parse_args()
    if not 1 <= args.top <= 20:
        raise ValueError("--top must be between 1 and 20")
    sys.stdout.reconfigure(encoding="utf-8")

    queries = json.loads(Path(args.queries).read_text(encoding="utf-8"))
    maps = json.loads(CHAPTERS.read_text(encoding="utf-8"))["editions"]
    by_edition: dict[str, list[dict]] = collections.defaultdict(list)
    for query in queries:
        for edition in dict.fromkeys(query.get("editions", [])):
            by_edition[str(edition)].append(query)

    output: dict[str, dict[str, list[tuple]]] = {}
    for edition, rows in by_edition.items():
        active_rows = [row for row in rows if any(str(term).strip() for term in row.get("terms", []))]
        if not active_rows:
            output[edition] = {str(row["key"]): [] for row in rows}
            continue
        page_map = maps.get(edition)
        if not page_map or not page_map.get("runs"):
            output[edition] = {str(row["key"]): [] for row in rows}
            continue
        try:
            pdf = open_verified_reference(edition, args.storage_root, args.mysql_defaults, args.database)
            chapter_coverage({"editions": {edition: page_map}}, edition, pdf.page_count)
            mapped_source = page_map.get("source_pdf_sha256")
            if not isinstance(mapped_source, str) or not re.fullmatch(r"[a-f0-9]{64}", mapped_source):
                raise ValueError("chapter map has no verified PDF source hash; rebuild it from the current PDF")
            if mapped_source != pdf.source_sha256:
                raise ValueError("chapter map belongs to a different PDF")
            output[edition] = search_edition(pdf, rows, page_map["runs"], args.top)
        except (ValueError, RuntimeError, OSError, subprocess.SubprocessError) as error:
            print(f"   {edition}: pending ({error})", file=sys.stderr)
            output[edition] = {str(row["key"]): [] for row in rows}

    for query in queries:
        key = str(query["key"])
        terms = [str(term) for term in query.get("terms", [])]
        print(f"## {key}  {' | '.join(terms)}")
        for edition in query.get("editions", []):
            matches = output.get(str(edition), {}).get(key, [])
            if not matches:
                print(f"   {edition}: no matching page from the current verified PDF")
            for score, _, page, chapter, excerpt, quote in matches:
                print(f"   {edition} p{page} ch{chapter} {score:5.1f} | {excerpt}")
                if quote:
                    print(f"      quote: {quote}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
