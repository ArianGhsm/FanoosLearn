#!/usr/bin/env python3
"""Read-only, reproducible 160-question periodontics TOPIC navigation crosswalk.

This is an evidence-tiered study/navigation overlay, NOT a book-page source
publication and NOT a claim that a missing Carranza14e full original was read.
14e contents are in the versioned official TOC catalog, but a complete 14e
page-marked text is unavailable. No bank table, official source or keys updated.

Input is a *private*, SHA-pinned, live production metadata snapshot, with only
question identity, stem hash, official answer status and original source pointer.
Output must stay in a private 0700 research directory (never publish questions).
"""
import argparse
import hashlib
import json
from collections import Counter
from pathlib import Path

EXPECTED_INPUT_SHA256 = "976cb162881419a1715f1c7c0333fb1be18bb7f3387f72c7e7722295986e5f76"
LATEST_TOC = "carranza-periodontology@14e"
REFERENCE_13E = "carranza-periodontology@13e"
REFERENCE_12E = "carranza-periodontology@12e"

# Human-reviewed *topic concordance* between prior full-edition chapters
# and the published 14e chapter titles; not an original-page quotation.
CROSSWALK_13E = {
    "3": "4", "5": "5", "8": "10", "11": "9", "12": "23",
    "13": "24", "14": "25", "15": "26", "16": "14",
    "17": "15", "18": "15", "19": "19", "20": "17",
    "23": "22", "24": "22", "25": "32", "32": "38",
    "33": "39", "34": "40", "35": "41", "45": "69",
    "46": "49", "47": "43", "48": "50", "49": "31",
    "50": "51", "51": "52", "52": "53", "59": "61",
    "60": "61", "62": "62", "64": "61", "65": "65",
    "69": "66", "70": "45", "72": "70", "87": "21",
}

# Every 1398 question gets a *topic* in the 14e TOC, regardless of the
# separate exact official 12e source approval or disputed recorded key.
TOPICS_1398 = {
    130: "21", 131: "39", 132: "32", 133: "21", 134: "38",
    135: "19", 136: "22", 137: "15", 138: "4", 139: "4",
    140: "24", 141: "19", 142: "17", 143: "69", 144: "50",
    145: "50", 146: "69", 147: "43", 148: "9", 149: "41",
}

# Individual questions for which the legacy 13e source chapter is known to
# be a poor topic fit, or the 14e structure separates formerly joined issues.
# The original source chapter stays IMMUTABLE in the live bank.
QUESTION_OVERRIDES_13E = {
    (1399, 119): ("50", "Targeted hygiene / Bass belongs to 14e plaque biofilm control."),
    (1400, 116): ("19", "Trauma-induced gingival enlargement, not abscess therapy."),
    (1400, 119): ("22", "Buttressing bone formation is a bone-loss architecture topic."),
    (1400, 121): ("24", "Palatogingival developmental groove is a local predisposition."),
    (1400, 129): ("35", "Splinting in bruxism belongs to occlusion/masticatory disorders."),
    (1401, 128): ("4", "Which molar furcation is most coronal is root anatomy."),
    (1402, 150): ("61", "Furcation root proximity with choice of root resection."),
    (1403, 119): ("67", "MRONJ treatment is medically complex care, not calculus."),
    (1403, 122): ("10", "PAAP+ bacterial group belongs to biofilm/microbiology."),
    (1403, 125): ("61", "Oxidized regenerated cellulose use in surgical sites."),
}

# Question-specific latest-TOC revision of the historical 14e placeholders.
QUESTION_OVERRIDES_14E = {
    (1404, 114): ("15", "Plaque association with gingivitis: biofilm-induced gingivitis."),
    (1405, 113): ("11", "IL-10 host-microbe cytokine response."),
    (1405, 117): ("14", "Salivary histatins in mucosal defence."),
    (1405, 118): ("28", "Menstrual-cycle related periodontal changes."),
    (1405, 119): ("28", "Menopause and female patient differential."),
    (1405, 121): ("24", "Enamel projections predispose to furcation periodontal defects."),
    (1405, 126): ("43", "Initial supragingival instrumentation is nonsurgical phase."),
}

# Under no circumstances promote TOC-only provisional topic mapping to exact
# year-specific source or page without independent approved original-PDF proof.
VERIFIED_PRINTED_PAGE = {
    (1398, n) for n in (134, 137, 141, 144)
} | {
    (1399, n) for n in (113, 114, 115, 118, 119, 120, 122, 123, 125, 126, 129)
}


def sha256(blob: bytes) -> str:
    return hashlib.sha256(blob).hexdigest()


def build(source: dict, toc: dict) -> dict:
    chapters = {str(num): title for num, title in toc["editions"][LATEST_TOC]["chapters"]}
    assert len(chapters) == 88, "Latest-edition chapter catalog changed; review crosswalk."
    rows = source["items"]
    assert len(rows) == 160
    assert len({(q["year"], q["number"]) for q in rows}) == 160
    assert {q["year"] for q in rows} == set(range(1398, 1406))
    assert all(sum(q["year"] == year for q in rows) == 20 for year in range(1398, 1406))
    assert set(TOPICS_1398) == {q["number"] for q in rows if q["year"] == 1398}
    assert len(VERIFIED_PRINTED_PAGE) == 15
    overrides_seen = set()
    counts = Counter()
    results = []
    for q in rows:
        year, number = q["year"], q["number"]
        pair = (year, number)
        edition = q["source_edition"]
        previous = q["source_chapter"]
        if year == 1398:
            assert edition in (None, "12e")
            topic = TOPICS_1398[number]
            note = "1398 exact Carranza12e question: 14e topic proposed from stem and TOC."
            method = "topic_review_12e_to_14e"
        elif 1399 <= year <= 1403:
            assert edition == "13e"
            assert previous in CROSSWALK_13E, (year, number, previous)
            topic = CROSSWALK_13E[previous]
            note = "13e source chapter crosswalked by topic to 14e TOC."
            method = "topic_crosswalk_13e_to_14e"
            if pair in QUESTION_OVERRIDES_13E:
                topic, note = QUESTION_OVERRIDES_13E[pair]
                overrides_seen.add(pair)
                method = "individual_stem_topic_override"
        else:
            assert edition == "14e"
            assert previous in chapters
            topic = previous
            note = "Historical 14e topic retained; original 14e textbook page NOT verified."
            method = "historical_14e_topic_provisional"
            if pair in QUESTION_OVERRIDES_14E:
                topic, note = QUESTION_OVERRIDES_14E[pair]
                overrides_seen.add(pair)
                method = "individual_stem_topic_override"
        assert topic in chapters, (pair, topic)
        assert q["question_id"] and len(q["stem_sha256"]) == 64
        approved_original = pair in VERIFIED_PRINTED_PAGE
        if approved_original:
            assert q["source_id"] is not None and q["source_page"] is not None
        if year == 1398 and number not in (134, 137, 141, 144):
            assert q["source_id"] is None, "Do not silently source missing 1398 key holds."
        # Distinguish topic map completion from official-scientific completion.
        rowspec = {
            "year": year, "number": number,
            "question_id": q["question_id"], "stem_sha256": q["stem_sha256"],
            "topic_reference": LATEST_TOC,
            "topic_chapter": topic, "topic_chapter_title": chapters[topic],
            "topic_classification": "provisional_toc_navigation_only",
            "method": method, "rationale": note,
            "official_edition_for_year": (
                REFERENCE_12E if year == 1398 else
                REFERENCE_13E if year <= 1403 else LATEST_TOC
            ),
            "original_existing_source_edition": edition,
            "original_existing_source_chapter": previous,
            "original_existing_source_page": q["source_page"],
            "original_exact_page_independently_validated": approved_original,
            "official_answer_status_unchanged": q["answer_status"],
            "official_answer_position_unchanged": q["answer_position"],
            "has_source_row": q["source_id"] is not None,
            "needs_human_answer_review": year == 1398 and not approved_original,
            "publication_eligible_as_official_year_reference": False,
        }
        results.append(rowspec)
        counts[method] += 1
    assert overrides_seen == set(QUESTION_OVERRIDES_13E) | set(QUESTION_OVERRIDES_14E)
    assert sum(x["original_exact_page_independently_validated"] for x in results) == 15
    assert sum(x["has_source_row"] for x in results) == 144
    assert len(results) == 160
    assert set(q["topic_chapter"] for q in results) <= set(chapters)
    return {
        "format": "fanoos.periodontics.latest-toc-topic-navigation/1",
        "edition": LATEST_TOC,
        "reference_basis": "14e VERSIONED CHAPTER CONTENTS ONLY, NOT COMPLETE ORIGINAL 14e TEXT",
        "scope": "nonproduction topic navigation research",
        "exact_source_or_page_changes": 0,
        "question_choice_answer_assessment_or_attempt_mutations": 0,
        "items_total": 160,
        "topic_chapter_assigned": 160,
        "official_exact_original_page_verified_previously": 15,
        "official_unverified_or_missing_source": 145,
        "method_counts": dict(sorted(counts.items())),
        "items": results,
    }


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", type=Path, required=True)
    ap.add_argument("--toc", type=Path, required=True)
    ap.add_argument("--out", type=Path, required=True)
    args = ap.parse_args()
    blob = args.input.read_bytes()
    if sha256(blob) != EXPECTED_INPUT_SHA256:
        raise SystemExit("Private live metadata snapshot SHA changed; re-audit before crosswalk.")
    try:
        source = json.loads(blob)
        toc = json.loads(args.toc.read_text())
        result = build(source, toc)
    except (ValueError, AssertionError, KeyError) as error:
        raise SystemExit(f"Refused: {error}")
    if args.out.exists() or args.out.is_symlink():
        raise SystemExit("Refused to overwrite prior research results.")
    if not str(args.out.resolve().parent).startswith("/srv/fanoos/shared/research/"):
        raise SystemExit("Output must stay in approved protected FANOOS research.")
    data = (json.dumps(result, ensure_ascii=False, indent=2) + "\n").encode()
    with args.out.open("xb") as f:
        f.write(data)
    args.out.chmod(0o600)
    print(json.dumps({
        "result": str(args.out), "sha256": sha256(data),
        "topic_mapped": result["topic_chapter_assigned"],
        "official_original_page_verified": 15,
        "source_database_mutations": 0,
        "method_counts": result["method_counts"],
    }))


if __name__ == "__main__":
    main()
