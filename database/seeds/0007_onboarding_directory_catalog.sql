SET NAMES utf8mb4;

-- Ported verbatim from legacy/site/api/bot_onboarding.php:12-127 (dent_bot_onboarding_catalog),
-- which the legacy bot used to drive its province -> institution onboarding steps.
-- Only geography + institution identity is ported here: majors/entry-years/admission-types
-- are wizard-time catalog values (AGENTS.md #1 forbids hardcoding program identity into
-- application logic), and programs/faculties are per-institution data an owner creates
-- through ClassProvisioningService, not a flat list that belongs in this seed.
--
-- Idempotency: every INSERT below matches on the real-world natural key (country code,
-- then province/city/institution name) via WHERE NOT EXISTS, never on the internally
-- generated id/code. Re-running this file is a no-op, and if an institution in this list
-- was already provisioned by an owner through ClassProvisioningService (matched by name),
-- this seed reuses that existing row instead of inserting a second one.
--
-- Missing city level: the legacy catalog only ever recorded province + institution name,
-- with no city column at all. FANOOS's directory models a city between province and
-- institution (directory_institutions.city_id is NOT NULL), so every institution needs
-- one. Inventing specific city names for 106 institutions from a province name alone
-- would fabricate data no source here actually provides (e.g. Fars province's capital is
-- Shiraz, not "Fars" -- guessing wrongly is worse than saying nothing). Instead, this seed
-- creates exactly one placeholder city per province, named "نامشخص" (unspecified), and
-- attaches every seeded institution in that province to it. This preserves full province
-- accuracy while being honest about the missing granularity, at the cost of city-level
-- browsing/reporting being meaningless for these rows until a real data-quality pass
-- backfills actual cities per institution.

INSERT INTO directory_countries (id, code, name, status, created_at, updated_at)
SELECT UUID(), 'IR', 'ایران', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
WHERE NOT EXISTS (SELECT 1 FROM directory_countries WHERE code = 'IR');

INSERT INTO directory_provinces (id, country_id, code, name, status, created_at, updated_at)
SELECT UUID(), country.id, province.code, province.name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM (
    SELECT 'hormozgan' AS `code`, 'هرمزگان' AS `name` UNION ALL
    SELECT 'hamedan', 'همدان' UNION ALL
    SELECT 'khorasan-shomali', 'خراسان شمالی' UNION ALL
    SELECT 'khuzestan', 'خوزستان' UNION ALL
    SELECT 'chaharmahal-bakhtiari', 'چهارمحال و بختیاری' UNION ALL
    SELECT 'khorasan-razavi', 'خراسان رضوی' UNION ALL
    SELECT 'sistan-baluchestan', 'سیستان و بلوچستان' UNION ALL
    SELECT 'ardabil', 'اردبیل' UNION ALL
    SELECT 'markazi', 'مرکزی' UNION ALL
    SELECT 'azarbaijan-gharbi', 'آذربایجان غربی' UNION ALL
    SELECT 'azarbaijan-sharghi', 'آذربایجان شرقی' UNION ALL
    SELECT 'kerman', 'کرمان' UNION ALL
    SELECT 'khorasan-jonubi', 'خراسان جنوبی' UNION ALL
    SELECT 'fars', 'فارس' UNION ALL
    SELECT 'tehran', 'تهران' UNION ALL
    SELECT 'isfahan', 'اصفهان' UNION ALL
    SELECT 'alborz', 'البرز' UNION ALL
    SELECT 'ilam', 'ایلام' UNION ALL
    SELECT 'mazandaran', 'مازندران' UNION ALL
    SELECT 'bushehr', 'بوشهر' UNION ALL
    SELECT 'zanjan', 'زنجان' UNION ALL
    SELECT 'semnan', 'سمنان' UNION ALL
    SELECT 'qazvin', 'قزوین' UNION ALL
    SELECT 'qom', 'قم' UNION ALL
    SELECT 'kurdistan', 'کردستان' UNION ALL
    SELECT 'kermanshah', 'کرمانشاه' UNION ALL
    SELECT 'golestan', 'گلستان' UNION ALL
    SELECT 'gilan', 'گیلان' UNION ALL
    SELECT 'lorestan', 'لرستان' UNION ALL
    SELECT 'kohgiluyeh-boyerahmad', 'کهگیلویه و بویراحمد' UNION ALL
    SELECT 'yazd', 'یزد'
) province
JOIN directory_countries country ON country.code = 'IR'
WHERE NOT EXISTS (
    SELECT 1 FROM directory_provinces existing
    WHERE existing.country_id = country.id AND existing.name = province.name
);

INSERT INTO directory_cities (id, province_id, code, name, status, created_at, updated_at)
SELECT UUID(), province.id, 'unspecified', 'نامشخص', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM directory_provinces province
JOIN directory_countries country ON country.id = province.country_id AND country.code = 'IR'
WHERE NOT EXISTS (
    SELECT 1 FROM directory_cities existing
    WHERE existing.province_id = province.id AND existing.code = 'unspecified'
);

INSERT INTO directory_institutions (id, city_id, slug, name, institution_type, status, created_at, updated_at)
SELECT UUID(), city.id, CONCAT('legacy-', LPAD(institution.ordinal, 3, '0')), institution.name,
       CASE WHEN institution.admission_system = 'azad' THEN 'azad_university' ELSE 'medical_university' END,
       'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM (
    SELECT 1 AS `ordinal`, 'هرمزگان' AS `province`, 'دانشکده علوم پزشکی شرق هرمزگان' AS `name`, 'public' AS `admission_system` UNION ALL
    SELECT 2, 'هرمزگان', 'دانشکده علوم پزشکی غرب هرمزگان', 'public' UNION ALL
    SELECT 3, 'همدان', 'دانشکده علوم پزشکی اسدآباد', 'public' UNION ALL
    SELECT 4, 'خراسان شمالی', 'دانشکده علوم پزشکی اسفراین', 'public' UNION ALL
    SELECT 5, 'خوزستان', 'دانشکده علوم پزشکی بهبهان', 'public' UNION ALL
    SELECT 6, 'چهارمحال و بختیاری', 'دانشکده علوم پزشکی بروجن', 'public' UNION ALL
    SELECT 7, 'خراسان رضوی', 'دانشکده علوم پزشکی تربت جام', 'public' UNION ALL
    SELECT 8, 'سیستان و بلوچستان', 'دانشکده علوم پزشکی چابهار', 'public' UNION ALL
    SELECT 9, 'اردبیل', 'دانشکده علوم پزشکی خلخال', 'public' UNION ALL
    SELECT 10, 'مرکزی', 'دانشکده علوم پزشکی خمین', 'public' UNION ALL
    SELECT 11, 'آذربایجان غربی', 'دانشکده علوم پزشکی خوی', 'public' UNION ALL
    SELECT 12, 'مرکزی', 'دانشکده علوم پزشکی ساوه', 'public' UNION ALL
    SELECT 13, 'آذربایجان شرقی', 'دانشکده علوم پزشکی سراب', 'public' UNION ALL
    SELECT 14, 'سیستان و بلوچستان', 'دانشکده علوم پزشکی سراوان', 'public' UNION ALL
    SELECT 15, 'کرمان', 'دانشکده علوم پزشکی سیرجان', 'public' UNION ALL
    SELECT 16, 'خوزستان', 'دانشکده علوم پزشکی شوشتر', 'public' UNION ALL
    SELECT 17, 'خراسان جنوبی', 'دانشکده علوم پزشکی فردوس', 'public' UNION ALL
    SELECT 18, 'خراسان جنوبی', 'دانشکده علوم پزشکی قائنات', 'public' UNION ALL
    SELECT 19, 'خراسان رضوی', 'دانشکده علوم پزشکی کاشمر', 'public' UNION ALL
    SELECT 20, 'فارس', 'دانشکده علوم پزشکی گراش', 'public' UNION ALL
    SELECT 21, 'فارس', 'دانشکده علوم پزشکی لارستان', 'public' UNION ALL
    SELECT 22, 'آذربایجان شرقی', 'دانشکده علوم پزشکی مراغه', 'public' UNION ALL
    SELECT 23, 'آذربایجان غربی', 'دانشکده علوم پزشکی میاندوآب', 'public' UNION ALL
    SELECT 24, 'تهران', 'دانشگاه علوم پزشکی ارتش جمهوری اسلامی ایران', 'public' UNION ALL
    SELECT 25, 'تهران', 'دانشگاه علوم پزشکی بقیةالله (عج)', 'public' UNION ALL
    SELECT 26, 'مرکزی', 'دانشگاه علوم پزشکی اراک', 'public' UNION ALL
    SELECT 27, 'اردبیل', 'دانشگاه علوم پزشکی اردبیل', 'public' UNION ALL
    SELECT 28, 'آذربایجان غربی', 'دانشگاه علوم پزشکی ارومیه', 'public' UNION ALL
    SELECT 29, 'اصفهان', 'دانشگاه علوم پزشکی اصفهان', 'public' UNION ALL
    SELECT 30, 'البرز', 'دانشگاه علوم پزشکی البرز', 'public' UNION ALL
    SELECT 31, 'خوزستان', 'دانشگاه علوم پزشکی جندی‌شاپور اهواز', 'public' UNION ALL
    SELECT 32, 'تهران', 'دانشگاه علوم پزشکی ایران', 'public' UNION ALL
    SELECT 33, 'سیستان و بلوچستان', 'دانشگاه علوم پزشکی ایرانشهر', 'public' UNION ALL
    SELECT 34, 'ایلام', 'دانشگاه علوم پزشکی ایلام', 'public' UNION ALL
    SELECT 35, 'خوزستان', 'دانشگاه علوم پزشکی آبادان', 'public' UNION ALL
    SELECT 36, 'مازندران', 'دانشگاه علوم پزشکی بابل', 'public' UNION ALL
    SELECT 37, 'خراسان شمالی', 'دانشگاه علوم پزشکی خراسان شمالی (بجنورد)', 'public' UNION ALL
    SELECT 38, 'کرمان', 'دانشگاه علوم پزشکی بم', 'public' UNION ALL
    SELECT 39, 'هرمزگان', 'دانشگاه علوم پزشکی هرمزگان (بندرعباس)', 'public' UNION ALL
    SELECT 40, 'بوشهر', 'دانشگاه علوم پزشکی بوشهر', 'public' UNION ALL
    SELECT 41, 'خراسان جنوبی', 'دانشگاه علوم پزشکی بیرجند', 'public' UNION ALL
    SELECT 42, 'آذربایجان شرقی', 'دانشگاه علوم پزشکی تبریز', 'public' UNION ALL
    SELECT 43, 'خراسان رضوی', 'دانشگاه علوم پزشکی تربت حیدریه', 'public' UNION ALL
    SELECT 44, 'تهران', 'دانشگاه علوم پزشکی تهران', 'public' UNION ALL
    SELECT 45, 'فارس', 'دانشگاه علوم پزشکی جهرم', 'public' UNION ALL
    SELECT 46, 'کرمان', 'دانشگاه علوم پزشکی جیرفت', 'public' UNION ALL
    SELECT 47, 'خوزستان', 'دانشگاه علوم پزشکی دزفول', 'public' UNION ALL
    SELECT 48, 'کرمان', 'دانشگاه علوم پزشکی رفسنجان', 'public' UNION ALL
    SELECT 49, 'سیستان و بلوچستان', 'دانشگاه علوم پزشکی زابل', 'public' UNION ALL
    SELECT 50, 'سیستان و بلوچستان', 'دانشگاه علوم پزشکی زاهدان', 'public' UNION ALL
    SELECT 51, 'زنجان', 'دانشگاه علوم پزشکی زنجان', 'public' UNION ALL
    SELECT 52, 'خراسان رضوی', 'دانشگاه علوم پزشکی سبزوار', 'public' UNION ALL
    SELECT 53, 'سمنان', 'دانشگاه علوم پزشکی سمنان', 'public' UNION ALL
    SELECT 54, 'سمنان', 'دانشگاه علوم پزشکی شاهرود', 'public' UNION ALL
    SELECT 55, 'چهارمحال و بختیاری', 'دانشگاه علوم پزشکی شهرکرد', 'public' UNION ALL
    SELECT 56, 'تهران', 'دانشگاه علوم پزشکی شهید بهشتی', 'public' UNION ALL
    SELECT 57, 'فارس', 'دانشگاه علوم پزشکی شیراز', 'public' UNION ALL
    SELECT 58, 'فارس', 'دانشگاه علوم پزشکی فسا', 'public' UNION ALL
    SELECT 59, 'قزوین', 'دانشگاه علوم پزشکی قزوین', 'public' UNION ALL
    SELECT 60, 'قم', 'دانشگاه علوم پزشکی قم', 'public' UNION ALL
    SELECT 61, 'اصفهان', 'دانشگاه علوم پزشکی کاشان', 'public' UNION ALL
    SELECT 62, 'کردستان', 'دانشگاه علوم پزشکی کردستان', 'public' UNION ALL
    SELECT 63, 'کرمان', 'دانشگاه علوم پزشکی کرمان', 'public' UNION ALL
    SELECT 64, 'کرمانشاه', 'دانشگاه علوم پزشکی کرمانشاه', 'public' UNION ALL
    SELECT 65, 'گلستان', 'دانشگاه علوم پزشکی گلستان', 'public' UNION ALL
    SELECT 66, 'خراسان رضوی', 'دانشگاه علوم پزشکی گناباد', 'public' UNION ALL
    SELECT 67, 'گیلان', 'دانشگاه علوم پزشکی گیلان', 'public' UNION ALL
    SELECT 68, 'لرستان', 'دانشگاه علوم پزشکی لرستان', 'public' UNION ALL
    SELECT 69, 'مازندران', 'دانشگاه علوم پزشکی مازندران', 'public' UNION ALL
    SELECT 70, 'خراسان رضوی', 'دانشگاه علوم پزشکی مشهد', 'public' UNION ALL
    SELECT 71, 'تهران', 'دانشگاه مجازی وزارت بهداشت', 'public' UNION ALL
    SELECT 72, 'خراسان رضوی', 'دانشگاه علوم پزشکی نیشابور', 'public' UNION ALL
    SELECT 73, 'همدان', 'دانشگاه علوم پزشکی همدان', 'public' UNION ALL
    SELECT 74, 'کهگیلویه و بویراحمد', 'دانشگاه علوم پزشکی یاسوج', 'public' UNION ALL
    SELECT 75, 'یزد', 'دانشگاه علوم پزشکی یزد', 'public' UNION ALL
    SELECT 76, 'آذربایجان شرقی', 'دانشگاه آزاد اسلامی واحد تبریز', 'azad' UNION ALL
    SELECT 77, 'آذربایجان شرقی', 'دانشگاه آزاد اسلامی واحد شبستر', 'azad' UNION ALL
    SELECT 78, 'آذربایجان شرقی', 'دانشگاه آزاد اسلامی واحد علوم پزشکی تبریز', 'azad' UNION ALL
    SELECT 79, 'آذربایجان غربی', 'دانشگاه آزاد اسلامی واحد ارومیه', 'azad' UNION ALL
    SELECT 80, 'اردبیل', 'دانشگاه آزاد اسلامی واحد اردبیل', 'azad' UNION ALL
    SELECT 81, 'اصفهان', 'دانشگاه آزاد اسلامی واحد خوراسگان', 'azad' UNION ALL
    SELECT 82, 'اصفهان', 'دانشگاه آزاد اسلامی واحد نجف‌آباد', 'azad' UNION ALL
    SELECT 83, 'البرز', 'دانشگاه آزاد اسلامی واحد کرج', 'azad' UNION ALL
    SELECT 84, 'تهران', 'دانشگاه علوم پزشکی آزاد اسلامی تهران', 'azad' UNION ALL
    SELECT 85, 'تهران', 'دانشگاه آزاد اسلامی واحد علوم و تحقیقات تهران', 'azad' UNION ALL
    SELECT 86, 'چهارمحال و بختیاری', 'دانشگاه آزاد اسلامی واحد شهرکرد', 'azad' UNION ALL
    SELECT 87, 'خراسان رضوی', 'دانشگاه آزاد اسلامی واحد علوم پزشکی مشهد', 'azad' UNION ALL
    SELECT 88, 'خراسان شمالی', 'دانشگاه آزاد اسلامی واحد بجنورد', 'azad' UNION ALL
    SELECT 89, 'خوزستان', 'دانشگاه آزاد اسلامی واحد شوشتر', 'azad' UNION ALL
    SELECT 90, 'سمنان', 'دانشگاه آزاد اسلامی واحد دامغان', 'azad' UNION ALL
    SELECT 91, 'سمنان', 'دانشگاه آزاد اسلامی واحد شاهرود', 'azad' UNION ALL
    SELECT 92, 'سمنان', 'دانشگاه آزاد اسلامی واحد گرمسار', 'azad' UNION ALL
    SELECT 93, 'سیستان و بلوچستان', 'دانشگاه آزاد اسلامی واحد زاهدان', 'azad' UNION ALL
    SELECT 94, 'فارس', 'دانشگاه آزاد اسلامی واحد شیراز', 'azad' UNION ALL
    SELECT 95, 'فارس', 'دانشگاه آزاد اسلامی واحد کازرون', 'azad' UNION ALL
    SELECT 96, 'قم', 'دانشگاه آزاد اسلامی واحد علوم پزشکی قم', 'azad' UNION ALL
    SELECT 97, 'کردستان', 'دانشگاه آزاد اسلامی واحد سنندج', 'azad' UNION ALL
    SELECT 98, 'کرمان', 'دانشگاه آزاد اسلامی واحد بافت', 'azad' UNION ALL
    SELECT 99, 'کرمان', 'دانشگاه آزاد اسلامی واحد کرمان', 'azad' UNION ALL
    SELECT 100, 'لرستان', 'دانشگاه آزاد اسلامی واحد بروجرد', 'azad' UNION ALL
    SELECT 101, 'مازندران', 'دانشگاه آزاد اسلامی واحد آیت‌الله آملی', 'azad' UNION ALL
    SELECT 102, 'مازندران', 'دانشگاه آزاد اسلامی واحد بابل', 'azad' UNION ALL
    SELECT 103, 'مازندران', 'دانشگاه آزاد اسلامی واحد تنکابن', 'azad' UNION ALL
    SELECT 104, 'مازندران', 'دانشگاه آزاد اسلامی واحد ساری', 'azad' UNION ALL
    SELECT 105, 'هرمزگان', 'دانشگاه آزاد اسلامی واحد خودگردان قشم', 'azad' UNION ALL
    SELECT 106, 'یزد', 'دانشگاه آزاد اسلامی واحد یزد', 'azad'
) institution
JOIN directory_provinces province ON province.name = institution.province
JOIN directory_countries country ON country.id = province.country_id AND country.code = 'IR'
JOIN directory_cities city ON city.province_id = province.id AND city.code = 'unspecified'
WHERE NOT EXISTS (
    SELECT 1 FROM directory_institutions existing WHERE existing.name = institution.name
);
