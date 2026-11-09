"""Deterministic query-term builder for Persian stems with English references."""
from __future__ import annotations

import re

LATIN = re.compile(r"[A-Za-z][A-Za-z0-9\-']*")
LATIN_STOP = set("the a an of and or in on to for with by is are be as at from that this which "
                 "all none above below following except not most least more less than".split())
# Number+unit is meaningful ("5 mm", "2%"); bare small numbers are weak.
UNIT_NUM = re.compile(r"(\d+(?:\.\d+)?)\s*(mm|cm|mg|ml|kg|gy|%|years?|weeks?|months?|days?|"
                      r"میلی‌?متر|سانتی‌?متر|میلی‌?گرم|درصد|سال|هفته|ماه|روز)", re.I)
UNIT_EN = {"میلیمتر": "mm", "میلی‌متر": "mm", "سانتیمتر": "cm", "سانتی‌متر": "cm", "میلیگرم": "mg",
           "میلی‌گرم": "mg", "درصد": "%", "سال": "years", "هفته": "weeks", "ماه": "months", "روز": "days"}
PERSIAN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
NEGATIVE_STEM = re.compile(r"به[‌ ]?جز|بجز|غیر از|نادرست|صحیح نیست|کدام.*نیست")

# Seed glossary: Persian dental transliterations/terms -> English. Extend per subject.
GLOSSARY = {
    "ماگزیلا": "maxilla", "مندیبل": "mandible", "کندیل": "condyle", "سمفیز": "symphysis",
    "غضروف": "cartilage", "مکل": "meckel", "کرودینگ": "crowding", "کراودینگ": "crowding",
    "اینسایزر": "incisor", "انسیزور": "incisor", "مولر": "molar", "پره‌مولر": "premolar", "پرمولر": "premolar",
    "لترال": "lateral", "کانین": "canine", "آلوئول": "alveolar", "شکاف لب و کام": "cleft lip palate",
    "پیوند استخوان": "bone graft", "تراکئوستومی": "tracheostomy", "ایزوتوپ": "isotope",
    "اشعه گاما": "gamma", "سفالومتریک": "cephalometric", "تلفظ": "speech", "لینگوورژن": "linguoversion",
    "انکوریج": "anchorage", "نیروی سنگین": "heavy force", "حرکت دندان": "tooth movement",
    "عصب منتال": "mental nerve", "استخوان‌سازی": "ossification", "جنینی": "embryo",
    "رشد": "growth", "چرخش": "rotation", "بافت نرم": "soft tissue", "عضله": "muscle",
    "ژنژیویت دسکواماتیو": "desquamative gingivitis", "پمفیگوس ولگاریس": "pemphigus vulgaris",
    "پمفیگوئید": "pemphigoid", "اریتم مولتی‌فرم": "erythema multiforme", "لیکن‌پلان": "lichen planus",
    "لیکن پلان": "lichen planus", "پلاسماسل": "plasma cell", "استوماتیت": "stomatitis",
    "بیوپسی": "biopsy", "تنباکو": "tobacco", "لوکوپلاکی": "leukoplakia", "وروکوز": "verrucous",
    "پرولیفراتیو": "proliferative", "استروئید": "corticosteroid", "کراتوآکانتوما": "keratoacanthoma",
    "لانگرهانس": "langerhans", "هیستیوسیتوزیس": "histiocytosis", "نوروفیبروماتوزیس": "neurofibromatosis",
    "لنفانژیوم": "lymphangioma", "اسکلروتراپی": "sclerotherapy", "کندیلوما": "condyloma",
    "آکومیناتوم": "acuminatum", "وروکا ولگاریس": "verruca vulgaris", "پاپیلوما": "papilloma",
    "مولوسکوم": "molluscum", "ضدانعقاد": "anticoagulant", "وارفارین": "warfarin",
    "کلوتریمازول": "clotrimazole", "میکونازول": "miconazole", "کتوکونازول": "ketoconazole",
    "فلوکونازول": "fluconazole", "ملانوزیس": "melanosis", "ملانواکانتوما": "melanoacanthoma",
    "سرب": "lead", "الکل": "alcohol", "سیگاری": "smoker", "سرطان": "cancer", "اسکواموس": "squamous",
    "هیپرپلازی اپی‌تلیال": "epithelial hyperplasia", "بیماری هک": "heck",
    "تحلیل لثه": "gingival recession", "عقب‌نشینی لثه": "gingival recession",
    "زیربغل": "axillary", "پریودنتیت": "periodontitis", "ژنژیویت": "gingivitis",
    "پریودنشیوم": "periodontium", "سمنتوم": "cementum", "عاج": "dentin", "مینا": "enamel",
    "پالپ": "pulp", "کیست": "cyst", "تومور": "tumor", "آملوبلاستوما": "ameloblastoma",
    "ادنتوژنیک": "odontogenic", "کراتوسیست": "keratocyst", "اوستئوسارکوما": "osteosarcoma",
    "لنفوم": "lymphoma", "ملانوم": "melanoma", "کاندیدیازیس": "candidiasis", "هرپس": "herpes",
    "آفت": "aphthous", "زروستومی": "xerostomia", "سندرم": "syndrome", "شوگرن": "sjogren",
    "غده بزاقی": "salivary gland", "پاروتید": "parotid", "متاستاز": "metastasis",
    "رادیوگرافی": "radiograph", "رادیولوسنت": "radiolucent", "رادیواپک": "radiopaque",
    "براکت": "bracket", "اکلوژن": "occlusion", "مال‌اکلوژن": "malocclusion", "اوربایت": "overbite",
    "اورجت": "overjet", "کراس‌بایت": "crossbite", "اپن‌بایت": "open bite", "دیستال": "distal",
    "مزیال": "mesial", "پلاک": "plaque", "جرم": "calculus", "دیابت": "diabetes", "بارداری": "pregnancy", "فرکل": "freckling", "SCC": "squamous cell carcinoma",
}


def normalize_fa(text: str) -> str:
    """Unify Arabic/Persian letters, digits and ZWNJ spacing so glossary keys match."""
    text = (text or "").replace("ي", "ی").replace("ك", "ک").replace("ة", "ه").replace("\u200f", "")
    text = text.translate(PERSIAN_DIGITS)
    return re.sub(r"\s*\u200c\s*", "\u200c", text)


def latin_terms(text: str) -> list[str]:
    """Split each Latin span into content unigrams plus adjacent bigrams.

    Whole spans ("Pemphigus vulgaris shows acantholysis") almost never occur
    verbatim on the page; unigrams+bigrams do, and BM25 combines them.
    """
    out = []
    for span in re.split(r"[^A-Za-z0-9\-' ]+", text):
        words = [w for w in LATIN.findall(span)]
        content = [w if w.isupper() and len(w) > 1 else w for w in words]
        for i, w in enumerate(content):
            if w.lower() not in LATIN_STOP and len(w) > 1:
                out.append(w)
            if i + 1 < len(content) and w.lower() not in LATIN_STOP \
                    and content[i + 1].lower() not in LATIN_STOP:
                out.append(w + " " + content[i + 1])
    return out


def deterministic_terms(stem: str, choices: dict, key: str) -> tuple[list, list]:
    """Return (weighted terms, negative terms) from stem, official answer and glossary."""
    negative_stem = bool(NEGATIVE_STEM.search(stem))
    stem = normalize_fa(stem)
    choices = {k: normalize_fa(v) for k, v in (choices or {}).items()}
    answer = choices.get(key, "") if key else ""
    others = [v for k, v in choices.items() if k != key]
    terms: list = []

    def harvest(text: str, weight: float):
        for m in latin_terms(text):
            # Bigrams are answer-bearing concepts; unigrams carry recall.
            terms.append((m, weight * (1.3 if " " in m else 1.0)))
        for fa, en in GLOSSARY.items():
            if normalize_fa(fa) in text:
                terms.append((en, weight * (1.3 if " " in en else 1.0)))
        for num, unit in UNIT_NUM.findall(text):
            terms.append((num + " " + UNIT_EN.get(unit, unit.lower()), 0.8 * weight))
        for num in re.findall(r"\b\d{1,3}\b", text):
            terms.append((num, 0.2 * weight))     # bare numbers are weak alone

    harvest(stem, 1.0)
    # For EXCEPT stems the key is the odd one out: give all choices equal weight.
    harvest(answer, 1.0 if negative_stem else 1.6)
    for other in others:
        harvest(other, 0.6 if negative_stem else 0.25)
    best: dict = {}
    for t, w in terms:          # keep the strongest weight per concept, first-seen order
        k = t.lower()
        if k not in best or w > best[k][1]:
            best[k] = (best[k][0] if k in best else t, w)
    return list(best.values()), []
