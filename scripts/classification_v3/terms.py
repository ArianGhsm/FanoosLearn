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


# ---- Cycle-1 trial (2026-11-09): Persian->English bridge grounded in dev-set
# failure causes (zero-term queries, 1-2 term queries). Domain-general dental /
# histopathology vocabulary only; never derived from a gold page. Generic
# clinical words carry a reduced weight so BM25 IDF, not volume, decides.
GLOSSARY_C1 = {
    "میکروسکوپی": "microscopic", "هیستوپاتولوژیک": "histopathologic", "هیستوپاتولوژی": "histopathologic",
    "هیستولوژیک": "histologic", "کارسینوم": "carcinoma", "سارکوم": "sarcoma", "آدنوکارسینوم": "adenocarcinoma",
    "اپی‌تلیوم": "epithelium", "اپی‌تلیال": "epithelial", "اپیتلیال": "epithelial", "اپیتلیوم": "epithelium",
    "کراتین": "keratin", "کراتینیزه": "keratinized", "پاراکراتین": "parakeratin", "ارتوکراتین": "orthokeratin",
    "دیسپلازی": "dysplasia", "متاپلازی": "metaplasia", "هیپرپلازی": "hyperplasia", "آتروفی": "atrophy",
    "نکروز": "necrosis", "التهاب": "inflammation", "التهابی": "inflammatory", "گرانولوم": "granuloma",
    "گرانولوماتوز": "granulomatous", "فیبروز": "fibrosis", "فیبروبلاست": "fibroblast", "کلاژن": "collagen",
    "کالژن": "collagen", "آمیلوئید": "amyloid", "کنگو": "congo red", "سولفات": "sulfate", "درماتان": "dermatan",
    "هپاران": "heparan", "موکوپلی‌ساکارید": "mucopolysaccharide", "لیزوزوم": "lysosomal", "لیزوزومی": "lysosomal",
    "تیروئید": "thyroid", "پاراتیروئید": "parathyroid", "سونوگرافی": "ultrasound", "اسکن": "scan",
    "واسکولاریتی": "vascularity", "عروقی": "vascular", "همانژیوم": "hemangioma", "ترابکول": "trabecula",
    "ترابکولار": "trabecular", "سینوزوئیدال": "sinusoidal", "استخوانی": "bone", "استخوان": "bone",
    "استئوبلاست": "osteoblast", "استئوکلاست": "osteoclast", "استئوئید": "osteoid", "ژنتیکی": "genetic",
    "ارثی": "hereditary", "اتوزومال": "autosomal", "غالب": "dominant", "مغلوب": "recessive", "جهش": "mutation",
    "ژن": "gene", "کروموزوم": "chromosome", "هیپرکروم": "hyperchromatic", "هیپرکروماتیک": "hyperchromatic",
    "پلئومورفیسم": "pleomorphism", "پلی‌مورف": "pleomorphic", "میتوز": "mitoses", "ترشحی": "secretory",
    "مجاری": "duct", "مجرا": "duct", "غدد": "gland", "غده": "gland", "بزاق": "saliva", "بزاقی": "salivary",
    "ساب‌مندیبولار": "submandibular", "زیرفکی": "submandibular", "زیرزبانی": "sublingual", "موکوسل": "mucocele",
    "رانولا": "ranula", "سیالادنیت": "sialadenitis", "سیالولیت": "sialolith", "پلئومورفیک": "pleomorphic",
    "آدنوما": "adenoma", "موکواپیدرموئید": "mucoepidermoid", "آدنوئید": "adenoid", "سیستیک": "cystic",
    "شفاف": "clear cell", "ژانت": "giant cell", "سلول ژانت": "giant cell", "سلول غول": "giant cell",
    "لنفوسیت": "lymphocyte", "نوتروفیل": "neutrophil", "ائوزینوفیل": "eosinophil", "ماکروفاژ": "macrophage",
    "پلاسماسل": "plasma cell", "ماست": "mast cell", "ایمونوهیستوشیمی": "immunohistochemical",
    "ایمونوفلورسانس": "immunofluorescence", "اتوایمیون": "autoimmune", "خودایمنی": "autoimmune",
    "آنتی‌بادی": "antibody", "ویروس": "virus", "ویروسی": "viral", "باکتری": "bacteria", "باکتریایی": "bacterial",
    "قارچی": "fungal", "سیفلیس": "syphilis", "سل": "tuberculosis", "توبرکلوزیس": "tuberculosis",
    "اکتینومایکوزیس": "actinomycosis", "هیستوپلاسموز": "histoplasmosis", "موکورمایکوزیس": "mucormycosis",
    "آسپرژیلوزیس": "aspergillosis", "واریسلا": "varicella", "زوستر": "zoster", "سیتومگالوویروس": "cytomegalovirus",
    "اپشتین": "epstein-barr", "پاپیلوماویروس": "papillomavirus", "سرخک": "measles", "اوریون": "mumps",
    "ایدز": "hiv", "کاپوسی": "kaposi", "فیبروما": "fibroma", "لیپوما": "lipoma", "نوروما": "neuroma",
    "شوانوما": "schwannoma", "نوروفیبروم": "neurofibroma", "رابدومیوسارکوم": "rhabdomyosarcoma",
    "پیوژنیک": "pyogenic", "گرانولوم پیوژنیک": "pyogenic granuloma", "اپولیس": "epulis",
    "فیبرودیسپلازی": "fibrous dysplasia", "دیسپلازی فیبرو": "fibrous dysplasia", "سمنتوبلاستوما": "cementoblastoma",
    "ادنتوما": "odontoma", "میکزوما": "myxoma", "فولیکولار": "follicular", "دنتی‌ژروس": "dentigerous",
    "دنتیژروس": "dentigerous", "رادیکولار": "radicular", "پری‌آپیکال": "periapical", "پریاپیکال": "periapical",
    "گلوبولوماگزیلاری": "globulomaxillary", "نازوپالاتین": "nasopalatine", "تیروگلوسال": "thyroglossal",
    "درموئید": "dermoid", "اپیدرموئید": "epidermoid", "برانکیال": "branchial", "استئومیلیت": "osteomyelitis",
    "استئونکروز": "osteonecrosis", "بیس‌فسفونات": "bisphosphonate", "پاژه": "paget", "هیپرپاراتیروئیدی": "hyperparathyroidism",
    "کروبیسم": "cherubism", "مولتیپل میلوما": "multiple myeloma", "میلوما": "myeloma", "لوسمی": "leukemia",
    "لوکمی": "leukemia", "آنمی": "anemia", "کم‌خونی": "anemia", "هموفیلی": "hemophilia", "ترومبوسیتوپنی": "thrombocytopenia",
    "پتشی": "petechiae", "پیگمانتاسیون": "pigmentation", "ملانین": "melanin", "خال": "nevus", "نووس": "nevus",
    "آمالگام": "amalgam", "تاتو": "tattoo", "آدیسون": "addison", "پوتز": "peutz-jeghers",
    "زخم": "ulcer", "اولسر": "ulcer", "وزیکول": "vesicle", "بولا": "bulla", "تاول": "bulla", "پاپول": "papule",
    "پلاک سفید": "white lesion", "اریتروپلاکی": "erythroplakia", "آکانتولیز": "acantholysis",
    "نیکولسکی": "nikolsky", "آکانتوز": "acanthosis", "اسپونژیوز": "spongiosis", "لوپوس": "lupus",
    "اسکلرودرمی": "scleroderma", "بهجت": "behcet", "کرون": "crohn", "سارکوئیدوز": "sarcoidosis",
    "آمیلوئیدوز": "amyloidosis", "زبان جغرافیایی": "geographic tongue", "زبان مویی": "hairy tongue",
    "زبان": "tongue", "لب": "lip", "کام": "palate", "لثه": "gingiva", "لثه‌ای": "gingival", "مخاط": "mucosa",
    "مخاطی": "mucosal", "گونه": "buccal", "کف دهان": "floor of mouth", "حلق": "pharynx", "لوزه": "tonsil",
    "فک": "jaw", "فک پایین": "mandible", "فک بالا": "maxilla", "سینوس": "sinus", "دندان": "tooth",
    "دندانی": "dental", "ریشه": "root", "تاج": "crown", "رویش": "eruption", "نهفته": "impacted",
    "آملوژنز": "amelogenesis", "دنتینوژنز": "dentinogenesis", "ایمپرفکتا": "imperfecta", "تارودونتیسم": "taurodontism",
    "هیپودونشیا": "hypodontia", "آنودونشیا": "anodontia", "دندان اضافه": "supernumerary", "مزیودنس": "mesiodens",
    "فلوئوروزیس": "fluorosis", "تتراسایکلین": "tetracycline", "سایش": "attrition", "اروژن": "erosion",
    "تحلیل ریشه": "root resorption", "تحلیل": "resorption", "آنکیلوز": "ankylosis", "هایپرسمنتوز": "hypercementosis",
    "پالپیت": "pulpitis", "آبسه": "abscess", "سلولیت": "cellulitis", "لودویگ": "ludwig",
    "پاکت": "pocket", "پاکت پریودنتال": "periodontal pocket", "چسبندگی": "attachment", "از دست رفتن چسبندگی": "attachment loss",
    "لیگامان پریودنتال": "periodontal ligament", "رباط پریودنتال": "periodontal ligament", "استخوان آلوئول": "alveolar bone",
    "فورکا": "furcation", "فورکیشن": "furcation", "تحرک": "mobility", "لقی": "mobility", "پروب": "probe",
    "پروبینگ": "probing", "خونریزی": "bleeding", "بیوفیلم": "biofilm", "میکروبی": "microbial",
    "اسکیلینگ": "scaling", "روت پلنینگ": "root planing", "جراحی": "surgery", "فلپ": "flap", "پیوند": "graft",
    "بازسازی": "regeneration", "هدایت‌شده": "guided tissue", "ایمپلنت": "implant", "پری‌ایمپلنتیت": "peri-implantitis",
    "اوسئواینتگریشن": "osseointegration", "کلرهگزیدین": "chlorhexidine", "آنتی‌بیوتیک": "antibiotic",
    "داکسی‌سایکلین": "doxycycline", "مترونیدازول": "metronidazole", "آموکسی‌سیلین": "amoxicillin",
    "سیکلوسپورین": "cyclosporine", "فنی‌توئین": "phenytoin", "نیفدیپین": "nifedipine", "بزرگی لثه": "gingival enlargement",
    "نوتروپنی": "neutropenia", "پاپیلون": "papillon-lefevre", "ژنژیویت نکروزان": "necrotizing gingivitis",
    "نکروزان": "necrotizing", "تهاجمی": "aggressive", "مزمن": "chronic", "حاد": "acute", "موضعی": "localized",
    "منتشر": "generalized", "سیگار": "smoking", "استعمال دخانیات": "smoking", "اکلوزال": "occlusal",
    "تروما": "trauma", "ترومای اکلوزالی": "occlusal trauma", "سایتوکاین": "cytokine", "اینترلوکین": "interleukin",
    "پروستاگلاندین": "prostaglandin", "ماتریکس متالوپروتئیناز": "matrix metalloproteinase", "کمپلمان": "complement",
    "ایمنی": "immune", "میزبان": "host", "پاتوژن": "pathogen", "پورفیروموناس": "porphyromonas",
    "اگریگاتیباکتر": "aggregatibacter", "اکتینومایستم‌کومیتانس": "actinomycetemcomitans", "ترپونما": "treponema",
    "تانرلا": "tannerella", "فوزوباکتریوم": "fusobacterium", "پروتلا": "prevotella", "اسپیروکت": "spirochete",
    "کراتر": "crater", "دبریدمان": "debridement", "ژنژیوکتومی": "gingivectomy", "ژنژیوپلاستی": "gingivoplasty",
    "مخاطی‌لثه‌ای": "mucogingival", "فرنکتومی": "frenectomy", "فرنوم": "frenum", "کانتور": "contour",
    "استئوپلاستی": "osteoplasty", "استئوکتومی": "ostectomy", "سمان": "cement", "رادیوگرافیک": "radiographic",
    "یائسگی": "menopause", "بلوغ": "puberty", "استرس": "stress", "اچ‌آی‌وی": "hiv", "قلبی": "cardiovascular",
    "پیوند عضو": "transplant", "شیمی‌درمانی": "chemotherapy", "رادیوتراپی": "radiation therapy", "پرتودرمانی": "radiation therapy",
}
# Generic clinical words: bridged but down-weighted.
GENERIC_C1 = {"microscopic", "histopathologic", "histologic", "bone", "tooth", "dental", "gland", "duct",
              "chronic", "acute", "inflammation", "inflammatory", "immune", "host", "surgery", "scan",
              "genetic", "localized", "generalized", "mucosa", "jaw", "root", "crown", "tongue", "lip"}


def _yi(text: str) -> str:
    """Collapse PDF-import letter doubling (e.g. 'یی' artefacts) for glossary matching only."""
    return re.sub(r"ی{2,}", "ی", text.replace("‌", ""))


def _has_fa(key: str, text: str) -> bool:
    """Short keys (<=4 letters, e.g. 'سل', 'کام', 'ژن') must be whole words, not substrings."""
    if len(key) > 4:
        return key in text
    return re.search(r"(?<![\u0600-\u06FF])" + re.escape(key) + r"(?![\u0600-\u06FF])", text) is not None


def c1_terms(stem: str, choices: dict, key: str) -> tuple[list, list]:
    """v3b terms + broader Persian bridge (generic words down-weighted)."""
    terms, neg = deterministic_terms(stem, choices, key)
    have = {t.lower() for t, _ in terms}
    negative_stem = bool(NEGATIVE_STEM.search(stem))
    stem_n = _yi(normalize_fa(stem))
    ch = {k: _yi(normalize_fa(v)) for k, v in (choices or {}).items()}
    parts = [(stem_n, 1.0)] + [(v, (1.0 if negative_stem else 1.6) if k == key else
                                   (0.6 if negative_stem else 0.25)) for k, v in ch.items()]
    glossary = {_yi(normalize_fa(fa)): en for fa, en in GLOSSARY_C1.items()}
    for text, w in parts:
        for fa, en in glossary.items():
            if fa and _has_fa(fa, text):
                ww = w * (0.5 if en in GENERIC_C1 else (1.3 if " " in en else 1.0))
                if en.lower() not in have:
                    terms.append((en, ww)); have.add(en.lower())
    return terms, neg


TRIAL_VARIANTS = {"c1_bridge": c1_terms}
