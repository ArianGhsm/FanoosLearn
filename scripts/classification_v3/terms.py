"""Deterministic query-term builder for Persian stems with English references."""
from __future__ import annotations

import re

LATIN = re.compile(r"[A-Za-z][A-Za-z0-9\-']+(?:\s+[A-Za-z][A-Za-z0-9\-']+)*")
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
    "سرب": "lead", "الکی": "alcoholic", "سیگاری": "smoker", "سرطان": "cancer", "اسکواموس": "squamous",
    "هیپرپلازی اپی‌تلیال": "epithelial hyperplasia", "بیماری هک": "heck",
    "تحلیل لثه": "gingival recession", "عقب‌نشینی لثه": "gingival recession",
    "زیربغل": "axillary", "فرکل": "freckling", "SCC": "squamous cell carcinoma",
}


def deterministic_terms(stem: str, choices: dict, key: str) -> tuple[list, list]:
    """Return (weighted terms, negative terms) from stem, official answer and glossary."""
    negative_stem = bool(NEGATIVE_STEM.search(stem))
    answer = choices.get(key, "") if key else ""
    others = [v for k, v in choices.items() if k != key]
    terms: list = []

    def harvest(text: str, weight: float):
        for m in LATIN.findall(text):
            terms.append((m, weight))
        for fa, en in GLOSSARY.items():
            if fa in text:
                terms.append((en, weight))
        for num in re.findall(r"\b\d{1,3}\b", text):
            terms.append((num, 0.3 * weight))     # numeric tokens are weak alone

    harvest(stem, 1.0)
    # For EXCEPT stems the key is the odd one out: give all choices equal weight.
    harvest(answer, 1.0 if negative_stem else 1.6)
    for other in others:
        harvest(other, 0.6 if negative_stem else 0.25)
    seen, out = set(), []
    for t, w in terms:
        k = t.lower()
        if k not in seen:
            seen.add(k); out.append((t, w))
    return out, []
