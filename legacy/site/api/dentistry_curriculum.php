<?php
declare(strict_types=1);

function dent_dentistry_curriculum_category_titles(): array
{
    return [
        'theory' => 'واحدهای نظری',
        'preclinic' => 'مبانی / پری‌کلینیک‌ها',
        'practical' => 'واحدهای عملی / بخش‌ها',
        'workshop' => 'واحدهای کارگاهی',
    ];
}

function dent_dentistry_term6_final_exam_schedule(): array
{
    return [
        'diagnostics-2' => [
            'title' => 'دندانپزشکی تشخیصی ۲',
            'jalaliDate' => '۱۴۰۵/۰۴/۲۹',
            'weekdayLabel' => 'دوشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-07-20T12:15:00+03:30',
            'courseSlugs' => ['diagnostics-2-term6'],
        ],
        'complete-prosthodontics-theory' => [
            'title' => 'پروتز کامل نظری',
            'jalaliDate' => '۱۴۰۵/۰۴/۳۱',
            'weekdayLabel' => 'چهارشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-07-22T12:15:00+03:30',
            'courseSlugs' => ['complete-foundations-theory-midterm-practice', 'complete-prosthodontics-midterm-sample', 'zarb-complete-prosthodontics'],
        ],
        'restorative-theory-1' => [
            'title' => 'ترمیمی نظری ۱',
            'jalaliDate' => '۱۴۰۵/۰۵/۰۳',
            'weekdayLabel' => 'شنبه',
            'timeLabel' => '۰۷:۴۵',
            'startsAt' => '2026-07-25T07:45:00+03:30',
            'courseSlugs' => ['restorative-theory-1-term6', 'restorative-theory-1-final-practice', 'restorative-theory-1-final-sample', 'restorative-theory-1-midterm-sample', 'summit-operative-dentistry', 'sturdevant-operative-dentistry'],
        ],
        'endodontics-foundations-1' => [
            'title' => 'مبانی اندودانتیکس ۱',
            'jalaliDate' => '۱۴۰۵/۰۵/۰۷',
            'weekdayLabel' => 'چهارشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-07-29T12:15:00+03:30',
            'courseSlugs' => ['endotorabinejad'],
        ],
        'surgery-practical-1' => [
            'title' => 'جراحی عملی ۱ (آسکی)',
            'jalaliDate' => '۱۴۰۵/۰۵/۱۱',
            'weekdayLabel' => 'یکشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-02T12:15:00+03:30',
            'courseSlugs' => ['peterson-oral-surgery', 'malamed-local-anesthesia'],
        ],
        'specialized-language-3-4' => [
            'title' => 'زبان تخصصی ۳ و ۴',
            'jalaliDate' => '۱۴۰۵/۰۵/۱۴',
            'weekdayLabel' => 'چهارشنبه',
            'timeLabel' => '۱۳:۰۰',
            'startsAt' => '2026-08-05T13:00:00+03:30',
            'courseSlugs' => [],
        ],
        'gerontology' => [
            'title' => 'سالمندشناسی',
            'jalaliDate' => '۱۴۰۵/۰۵/۱۷',
            'weekdayLabel' => 'شنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-08T12:15:00+03:30',
            'courseSlugs' => ['gerontology-term-6', 'gerontology-term-6-final-sample'],
        ],
        'equipment-ergonomics' => [
            'title' => 'تجهیزات دندانپزشکی و ارگونومی',
            'jalaliDate' => '۱۴۰۵/۰۵/۲۰',
            'weekdayLabel' => 'سه‌شنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-11T12:15:00+03:30',
            'courseSlugs' => ['equipment-ergonomics-term6-final-sample'],
        ],
        'diagnostics-1' => [
            'title' => 'دندانپزشکی تشخیصی ۱',
            'jalaliDate' => '۱۴۰۵/۰۵/۲۴',
            'weekdayLabel' => 'شنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-15T12:15:00+03:30',
            'courseSlugs' => ['diagnostics-1-term6'],
        ],
        'research-methods-1' => [
            'title' => 'روش‌شناسی تحقیق ۱',
            'jalaliDate' => '۱۴۰۵/۰۵/۲۸',
            'weekdayLabel' => 'چهارشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-19T12:15:00+03:30',
            'courseSlugs' => [],
        ],
        'dental-materials-foundations' => [
            'title' => 'مبانی مواد دندانی',
            'jalaliDate' => '۱۴۰۵/۰۵/۳۱',
            'weekdayLabel' => 'شنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-22T12:15:00+03:30',
            'courseSlugs' => ['dental-materials-foundations', 'dental-materials-midterm-sample', 'dental-materials-foundations-final-sample', 'craig-dental-materials', 'vannoort-dental-materials'],
        ],
        'medical-emergencies' => [
            'title' => 'فوریت‌های پزشکی در دندانپزشکی',
            'jalaliDate' => '۱۴۰۵/۰۶/۰۲',
            'weekdayLabel' => 'دوشنبه',
            'timeLabel' => '۱۲:۱۵',
            'startsAt' => '2026-08-24T12:15:00+03:30',
            'courseSlugs' => ['malamed-medical-emergencies', 'medical-emergencies-term6-final-sample'],
        ],
    ];
}

function dent_dentistry_normalize_schedule_cohort_key(string $cohortKey): string
{
    $clean = trim(strtolower($cohortKey));
    if ($clean === '' || $clean === 'main' || $clean === '1402') {
        return 'dentistry-1402';
    }
    if (preg_match('/^\d{4}$/', $clean) === 1) {
        return 'dentistry-' . $clean;
    }
    return $clean;
}

function dent_dentistry_final_exam_payload(array $record, ?DateTimeImmutable $now = null): ?array
{
    $startsAtRaw = trim((string) ($record['startsAt'] ?? ''));
    if ($startsAtRaw === '') {
        return null;
    }

    try {
        $startsAt = new DateTimeImmutable($startsAtRaw);
        $expiresAt = $startsAt->setTime(0, 0)->modify('+1 day');
        $comparisonTime = $now ?? new DateTimeImmutable('now', $startsAt->getTimezone());
    } catch (Throwable $error) {
        return null;
    }

    $jalaliDate = trim((string) ($record['jalaliDate'] ?? ''));
    return [
        'cohortKey' => 'dentistry-1402',
        'cohortLabel' => 'ورودی ۱۴۰۲',
        'title' => (string) ($record['title'] ?? ''),
        'jalaliDate' => $jalaliDate,
        'weekdayLabel' => (string) ($record['weekdayLabel'] ?? ''),
        'timeLabel' => (string) ($record['timeLabel'] ?? ''),
        'startsAt' => $startsAt->format(DATE_ATOM),
        'expiresAt' => $expiresAt->format(DATE_ATOM),
        'hasPassed' => $comparisonTime->getTimestamp() >= $expiresAt->getTimestamp(),
        'displayLabel' => 'تاریخ آزمون پایان ترم برای ورودی ۱۴۰۲: ' . $jalaliDate,
    ];
}

function dent_dentistry_curriculum_final_exams(
    array $unit,
    string $cohortKey,
    string $courseSlug = '',
    ?DateTimeImmutable $now = null
): array {
    if (dent_dentistry_normalize_schedule_cohort_key($cohortKey) !== 'dentistry-1402') {
        return [];
    }

    $schedule = dent_dentistry_term6_final_exam_schedule();
    $keys = is_array($unit['finalExamKeys'] ?? null) ? $unit['finalExamKeys'] : [];
    $cleanCourseSlug = trim(strtolower($courseSlug));
    $payloads = [];
    foreach ($keys as $key) {
        $record = is_array($schedule[(string) $key] ?? null) ? $schedule[(string) $key] : null;
        if ($record === null) {
            continue;
        }

        $courseSlugs = array_values(array_filter(array_map('strval', is_array($record['courseSlugs'] ?? null) ? $record['courseSlugs'] : [])));
        if ($cleanCourseSlug !== '' && ($courseSlugs === [] || !in_array($cleanCourseSlug, $courseSlugs, true))) {
            continue;
        }

        $payload = dent_dentistry_final_exam_payload($record, $now);
        if ($payload !== null) {
            $payloads[] = $payload;
        }
    }

    return $payloads;
}

function dent_dentistry_select_home_active_exam_courses(array $sortedCourses, int $limit = 2): array
{
    $latestCourses = array_slice(array_values($sortedCourses), 0, max(0, $limit));
    return array_values(array_filter($latestCourses, static function ($course): bool {
        if (!is_array($course)) {
            return false;
        }
        $curriculum = is_array($course['curriculum'] ?? null) ? $course['curriculum'] : [];
        $finalExam = is_array($curriculum['finalExam'] ?? null) ? $curriculum['finalExam'] : null;
        return $finalExam === null || empty($finalExam['hasPassed']);
    }));
}

function dent_dentistry_exam_reference_specialties(): array
{
    return [
        [
            'key' => 'endodontics',
            'title' => 'اندودانتیکس',
            'references' => [
                [
                    'key' => 'torabinejad-endodontics-principles-practice-2021',
                    'title' => 'اصول و درمان اندودانتیکس',
                    'sourceTitle' => 'Torabinejad M, Fouad AF, Shabahang S. Endodontics: Principles and Practice',
                    'year' => 2021,
                    'editionLabel' => 'ویرایش ششم',
                    'courseSlugs' => ['endotorabinejad'],
                ],
            ],
        ],
        [
            'key' => 'oral-medicine',
            'title' => 'بیماری‌های دهان و فک و صورت',
            'references' => [
                [
                    'key' => 'burkets-oral-medicine-2021',
                    'title' => 'طب دهان برکت',
                    'sourceTitle' => "Glick M. Burket's Oral Medicine",
                    'year' => 2021,
                    'editionLabel' => 'ویرایش سیزدهم',
                    'courseSlugs' => ['burket-oral-medicine'],
                ],
                [
                    'key' => 'dental-management-medically-compromised-patient-2024',
                    'title' => 'مدیریت دندان‌پزشکی بیمار دارای ملاحظات پزشکی',
                    'sourceTitle' => 'Falace DA, Little J. Dental Management in the Medically Compromised Patient',
                    'year' => 2024,
                    'editionLabel' => 'ویرایش دهم',
                    'courseSlugs' => ['falace-medically-compromised'],
                ],
            ],
        ],
        [
            'key' => 'dental-materials',
            'title' => 'مواد دندانی',
            'references' => [
                [
                    'key' => 'craigs-restorative-dental-materials',
                    'title' => 'مواد دندانی ترمیمی کریگ',
                    'sourceTitle' => "Craig's Restorative Dental Materials",
                    'year' => 0,
                    'editionLabel' => '',
                    'courseSlugs' => ['craig-dental-materials'],
                ],
                [
                    'key' => 'van-noorts-introduction-dental-materials',
                    'title' => 'مقدمه مواد دندانی ون نورت',
                    'sourceTitle' => 'Introduction to Dental Materials',
                    'year' => 0,
                    'editionLabel' => '',
                    'courseSlugs' => ['vannoort-dental-materials'],
                ],
            ],
        ],
        [
            'key' => 'periodontics',
            'title' => 'پریودانتیکس',
            'references' => [
                [
                    'key' => 'carranzas-clinical-periodontology-2023',
                    'title' => 'پریودنتولوژی بالینی کارانزا',
                    'sourceTitle' => "Carranza's Clinical Periodontology",
                    'year' => 2023,
                    'editionLabel' => 'ویرایش چهاردهم',
                    'courseSlugs' => [],
                ],
            ],
        ],
        [
            'key' => 'pediatric-dentistry',
            'title' => 'دندان‌پزشکی کودکان',
            'references' => [
                [
                    'key' => 'mcdonald-avery-dentistry-child-adolescent-2021',
                    'title' => 'دندان‌پزشکی کودک و نوجوان مک‌دونالد و اوری',
                    'sourceTitle' => "McDonald J. and Avery's Dentistry for the Child and Adolescent",
                    'year' => 2021,
                    'editionLabel' => 'ویرایش یازدهم',
                    'courseSlugs' => [],
                ],
            ],
        ],
        [
            'key' => 'oral-radiology',
            'title' => 'رادیولوژی دهان و فک و صورت',
            'references' => [
                [
                    'key' => 'white-pharoah-oral-radiology-2019',
                    'title' => 'رادیولوژی دهان؛ اصول و تفسیر',
                    'sourceTitle' => 'White SC, Pharoah MJ. Oral Radiology, Principles and Interpretation',
                    'year' => 2019,
                    'editionLabel' => 'ویرایش هشتم',
                    'courseSlugs' => ['radiology2-whitepharoah', 'whitepharoah-radiology-term6'],
                ],
            ],
        ],
        [
            'key' => 'prosthodontics',
            'title' => 'پروتزهای دندانی',
            'references' => [
                [
                    'key' => 'shillingburg-fundamentals-fixed-prosthodontics-2012',
                    'title' => 'مبانی پروتز ثابت شیلینگبرگ',
                    'sourceTitle' => 'Schillingberg HT. Fundamentals of Fixed Prosthodontics',
                    'year' => 2012,
                    'editionLabel' => 'ویرایش چهارم',
                    'courseSlugs' => [],
                ],
                [
                    'key' => 'mccracken-removable-partial-prosthodontics-2016',
                    'title' => 'پروتز پارسیل متحرک مک‌کراکن',
                    'sourceTitle' => "Carr AB. McCracken's Removable Partial Prosthodontics",
                    'year' => 2016,
                    'editionLabel' => 'ویرایش سیزدهم',
                    'courseSlugs' => [],
                ],
                [
                    'key' => 'rosentiel-contemporary-fixed-prosthodontics-2023',
                    'title' => 'پروتز ثابت معاصر روزنستیل',
                    'sourceTitle' => 'Rosentiel S.F. Contemporary Fixed Prosthodontics',
                    'year' => 2023,
                    'editionLabel' => 'ویرایش ششم',
                    'courseSlugs' => [],
                ],
                [
                    'key' => 'zarb-prosthodontic-treatment-edentulous-patients-2013',
                    'title' => 'درمان پروتز بیماران بی‌دندان زارب و هابکرک',
                    'sourceTitle' => 'Zarb G, Hobkirk J. Prosthodontic Treatment for Edentulous Patients',
                    'year' => 2013,
                    'editionLabel' => 'ویرایش سیزدهم',
                    'courseSlugs' => ['zarb-complete-prosthodontics'],
                ],
            ],
        ],
        [
            'key' => 'oral-surgery',
            'title' => 'جراحی دهان و فک و صورت',
            'references' => [
                [
                    'key' => 'hupp-contemporary-oral-maxillofacial-surgery-2019',
                    'title' => 'جراحی دهان و فک و صورت معاصر',
                    'sourceTitle' => 'Contemporary Oral and Maxillofacial Surgery, James Hupp',
                    'year' => 2019,
                    'editionLabel' => 'ویرایش هفتم',
                    'courseSlugs' => ['peterson-oral-surgery'],
                ],
                [
                    'key' => 'malamed-handbook-local-anesthesia-2019',
                    'title' => 'راهنمای بی‌حسی موضعی مالامد',
                    'sourceTitle' => 'Malamed S. Handbook of Local Anesthesia',
                    'year' => 2019,
                    'editionLabel' => 'ویرایش هفتم',
                    'courseSlugs' => ['malamed-local-anesthesia'],
                ],
                [
                    'key' => 'malamed-medical-emergencies-dental-office',
                    'title' => 'اورژانس‌های پزشکی در مطب دندان‌پزشکی مالامد',
                    'sourceTitle' => 'Malamed S. Medical Emergencies in the Dental Office',
                    'year' => 0,
                    'editionLabel' => 'ویرایش هشتم',
                    'courseSlugs' => ['malamed-medical-emergencies'],
                ],
            ],
        ],
        [
            'key' => 'operative-dentistry',
            'title' => 'دندان‌پزشکی ترمیمی',
            'references' => [
                [
                    'key' => 'ritter-sturdevants-art-science-operative-dentistry-2018',
                    'title' => 'هنر و علم دندان‌پزشکی ترمیمی استردوانت',
                    'sourceTitle' => "Andre Ritter. Sturdevant's Art and Science of Operative Dentistry",
                    'year' => 2018,
                    'editionLabel' => 'ویرایش هفتم',
                    'courseSlugs' => ['sturdevant-operative-dentistry'],
                ],
                [
                    'key' => 'summits-fundamentals-operative-dentistry-2013',
                    'title' => 'مبانی دندان‌پزشکی ترمیمی سامیت',
                    'sourceTitle' => "Summit's Fundamentals of Operative Dentistry: A Contemporary Approach",
                    'year' => 2013,
                    'editionLabel' => 'ویرایش چهارم',
                    'courseSlugs' => ['summit-operative-dentistry'],
                ],
            ],
        ],
        [
            'key' => 'oral-pathology',
            'title' => 'آسیب‌شناسی دهان و فک و صورت',
            'references' => [
                [
                    'key' => 'neville-oral-maxillofacial-pathology-2024',
                    'title' => 'آسیب‌شناسی دهان و فک و صورت نویل',
                    'sourceTitle' => 'Neville B, Damm DD. Oral and Maxillofacial Pathology',
                    'year' => 2024,
                    'editionLabel' => 'ویرایش پنجم',
                    'courseSlugs' => [],
                ],
            ],
        ],
        [
            'key' => 'orthodontics',
            'title' => 'ارتودانتیکس',
            'references' => [
                [
                    'key' => 'proffit-contemporary-orthodontics-2019',
                    'title' => 'ارتودنسی معاصر پروفیت',
                    'sourceTitle' => 'Contemporary Orthodontics. William R. Proffit',
                    'year' => 2019,
                    'editionLabel' => 'ویرایش ششم',
                    'courseSlugs' => [],
                ],
            ],
        ],
    ];
}

function dent_dentistry_curriculum_terms(): array
{
    static $terms = null;
    if (is_array($terms)) {
        return $terms;
    }

    $titles = dent_dentistry_curriculum_category_titles();

    $terms = [
        [
            'number' => 4,
            'label' => 'ترم ۴',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        [
                            'key' => 'radiology-theory-1',
                            'title' => 'رادیو نظری ۱',
                            'aliases' => ['رادیولوژی نظری ۱', 'نمونه سوالات رادیولوژی نظری ۱'],
                            'resourceAliases' => ['جزوات رادیولوژی نظری ۱'],
                            'examCourseSlugs' => ['radiology1'],
                        ],
                        ['key' => 'tooth-tissue-health-disease', 'title' => 'بافت دندان در سلامت و بیماری'],
                        [
                            'key' => 'anatomy-morphology-theory',
                            'title' => 'آناتومی و مورفولوژی نظری',
                            'aliases' => ['مورفولوژی', 'آناتومی و مورفولوژی'],
                            'examCourseSlugs' => ['morphology'],
                        ],
                        ['key' => 'specialized-language-1-2', 'title' => 'زبان تخصصی ۱ و ۲'],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'morphology-preclinic', 'title' => 'پری‌کلینیک مورفولوژی'],
                    ],
                ],
            ],
        ],
        [
            'number' => 5,
            'label' => 'ترم ۵',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        [
                            'key' => 'radiology-theory-2',
                            'title' => 'رادیو نظری ۲',
                            'aliases' => ['رادیولوژی نظری ۲', 'نمونه سوالات رادیولوژی نظری ۲'],
                            'resourceAliases' => ['جزوات رادیولوژی نظری ۲'],
                            'examCourseSlugs' => ['radiology2', 'radiology2-whitepharoah'],
                        ],
                        ['key' => 'oral-health-theory-1', 'title' => 'سلامت دهان نظری ۱'],
                        [
                            'key' => 'systemic-diseases-1',
                            'title' => 'بیماری‌های سیستمیک ۱',
                            'aliases' => ['جزوات بیماری‌های سیستمیک', 'سیستمیک'],
                            'resourceAliases' => ['جزوات بیماری های سیستمیک'],
                            'examCourseSlugs' => ['systemicdiseases'],
                        ],
                        ['key' => 'surgery-theory-1', 'title' => 'جراحی نظری ۱'],
                        [
                            'key' => 'pulp-periapical-complex',
                            'title' => 'کمپلکس پالپ و پری‌اپیکال',
                            'aliases' => ['اندو ترابی‌نژاد'],
                            'resourceAliases' => [
                                'جزوات کمپلکس پالپ و پری‌اپیکال',
                                'ترابی‌نژاد',
                                'ترابی نژاد 2021',
                                'cdr ترابی‌نژاد',
                            ],
                        ],
                        [
                            'key' => 'ethics-communication',
                            'title' => 'اخلاق و مهارت‌های ارتباطی',
                            'resourceAliases' => ['اخلاق پزشکی'],
                        ],
                        [
                            'key' => 'pharmacology',
                            'title' => 'فارماکولوژی',
                            'aliases' => ['جزوات فارماکولوژی'],
                            'resourceAliases' => ['فارماکولوژی'],
                            'examCourseSlugs' => ['pharmacology'],
                        ],
                        [
                            'key' => 'restorative-foundations-theory',
                            'title' => 'تئوری مبانی ترمیمی',
                            'resourceAliases' => ['آرت اند ساینس', 'art and science'],
                        ],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'restorative-preclinic', 'title' => 'پری‌کلینیک ترمیمی'],
                        [
                            'key' => 'complete-partial-prosthesis-preclinic',
                            'title' => 'پری‌کلینیک پروتز کامل و پارسیل',
                            'aliases' => [
                                'کتاب گام‌به‌گام با پروتز پارسیل',
                                'مبانی پروتز کامل عملی',
                                'مبانی پروتز پارسیل عملی',
                            ],
                            'examCourseSlugs' => ['completeprosthesis', 'partialprosthesis'],
                        ],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'radiology-practical-1', 'title' => 'رادیو عملی ۱'],
                        ['key' => 'infection-control', 'title' => 'کنترل عفونت'],
                        ['key' => 'local-anesthesia', 'title' => 'بی‌حسی موضعی'],
                    ],
                ],
            ],
        ],
        [
            'number' => 6,
            'label' => 'ترم ۶',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        [
                            'key' => 'dental-materials-foundations',
                            'title' => 'مبانی مواد دندانی',
                            'finalExamKeys' => ['dental-materials-foundations'],
                            'examCourseSlugs' => ['dental-materials-foundations', 'dental-materials-midterm-sample', 'dental-materials-foundations-final-sample', 'craig-dental-materials', 'vannoort-dental-materials'],
                        ],
                        [
                            'key' => 'diagnostics-1-2',
                            'title' => 'تشخیصی ۱ و ۲',
                            'finalExamKeys' => ['diagnostics-2', 'diagnostics-1'],
                            'examCourseSlugs' => ['diagnostics-2-term6', 'diagnostics-2-final-sample', 'diagnostics-1-term6'],
                        ],
                        [
                            'key' => 'complete-foundations-theory',
                            'title' => 'پروتز کامل نظری',
                            'aliases' => ['مبانی کامل نظری'],
                            'finalExamKeys' => ['complete-prosthodontics-theory'],
                            'examCourseSlugs' => ['complete-foundations-theory-midterm-practice', 'complete-prosthodontics-midterm-sample', 'zarb-complete-prosthodontics'],
                        ],
                        [
                            'key' => 'restorative-theory-1',
                            'title' => 'ترمیمی نظری ۱',
                            'finalExamKeys' => ['restorative-theory-1'],
                            'resourceAliases' => ['سامیت', 'summitt'],
                            'examCourseSlugs' => ['restorative-theory-1-term6', 'restorative-theory-1-final-practice', 'restorative-theory-1-final-sample', 'restorative-theory-1-midterm-sample', 'summit-operative-dentistry', 'sturdevant-operative-dentistry'],
                        ],
                        [
                            'key' => 'medical-emergencies',
                            'title' => 'فوریت‌های پزشکی در دندانپزشکی',
                            'aliases' => ['فوریت‌های پزشکی'],
                            'finalExamKeys' => ['medical-emergencies'],
                            'examCourseSlugs' => ['malamed-medical-emergencies', 'medical-emergencies-term6-final-sample'],
                        ],
                        ['key' => 'gerontology-term-6', 'title' => 'سالمندشناسی', 'finalExamKeys' => ['gerontology'], 'examCourseSlugs' => ['gerontology-term-6', 'gerontology-term-6-final-sample']],
                        [
                            'key' => 'equipment-ergonomics',
                            'title' => 'تجهیزات دندان‌پزشکی و ارگونومی',
                            'finalExamKeys' => ['equipment-ergonomics'],
                            'resourceAliases' => ['تمامی پاور های تجهیزات', 'تجهیزات و ارگونومی'],
                            'examCourseSlugs' => ['equipment-ergonomics-term6-final-sample'],
                        ],
                        ['key' => 'research-methods-1-theory', 'title' => 'روش‌شناسی تحقیق ۱', 'aliases' => ['روش تحقیق ۱'], 'finalExamKeys' => ['research-methods-1']],
                        ['key' => 'specialized-language-3-4', 'title' => 'زبان تخصصی ۳ و ۴', 'finalExamKeys' => ['specialized-language-3-4']],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        [
                            'key' => 'endo-preclinic-1',
                            'title' => 'مبانی اندودانتیکس ۱',
                            'finalExamKeys' => ['endodontics-foundations-1'],
                            'aliases' => ['پری‌کلینیک اندو ۱', 'اندو ترابی‌نژاد', 'ترابی‌نژاد'],
                            'examCourseSlugs' => ['endotorabinejad'],
                        ],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        [
                            'key' => 'radiology-practical-2',
                            'title' => 'رادیو عملی ۲',
                            'examCourseSlugs' => ['whitepharoah-radiology-term6'],
                        ],
                        ['key' => 'restorative-practical-1', 'title' => 'ترمیمی عملی ۱'],
                        ['key' => 'oral-health-practical-1', 'title' => 'سلامت دهان عملی ۱'],
                        [
                            'key' => 'surgery-practical-1',
                            'title' => 'جراحی عملی ۱',
                            'finalExamKeys' => ['surgery-practical-1'],
                            'examCourseSlugs' => ['surgery-practical-1-exit-practice', 'peterson-oral-surgery', 'malamed-local-anesthesia'],
                        ],
                        ['key' => 'complete-prosthesis-practical-1', 'title' => 'پروتز کامل عملی ۱'],
                        ['key' => 'equipment-practical', 'title' => 'تجهیزات'],
                    ],
                ],
                [
                    'key' => 'workshop',
                    'title' => $titles['workshop'],
                    'units' => [
                        ['key' => 'research-methods-1-workshop', 'title' => 'روش‌شناسی تحقیق ۱', 'aliases' => ['روش تحقیق ۱'], 'finalExamKeys' => ['research-methods-1']],
                    ],
                ],
            ],
        ],
        [
            'number' => 7,
            'label' => 'ترم ۷',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        ['key' => 'perio-theory-1', 'title' => 'پریو نظری ۱'],
                        ['key' => 'diagnostics-3', 'title' => 'تشخیصی ۳'],
                        ['key' => 'ent', 'title' => 'گوش و حلق و بینی'],
                        ['key' => 'ortho-theory-1', 'title' => 'ارتو نظری ۱'],
                        ['key' => 'partial-foundations-theory', 'title' => 'مبانی پارسیل نظری'],
                        ['key' => 'endo-theory-1', 'title' => 'اندو نظری ۱'],
                        ['key' => 'research-methods-2-theory', 'title' => 'روش تحقیق ۲'],
                        ['key' => 'oral-health-theory-2', 'title' => 'سلامت دهان نظری ۲'],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'endo-preclinic-2', 'title' => 'پری‌کلینیک اندو ۲'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'disease-practical-1', 'title' => 'بیماری عملی ۱'],
                        ['key' => 'restorative-practical-2', 'title' => 'ترمیمی عملی ۲'],
                        ['key' => 'oral-health-practical-2', 'title' => 'سلامت دهان عملی ۲'],
                        ['key' => 'path-practical-1', 'title' => 'پاتو عملی ۱'],
                        ['key' => 'partial-prosthesis-practical-1', 'title' => 'پروتز پارسیل عملی ۱'],
                        ['key' => 'surgery-practical-2', 'title' => 'جراحی عملی ۲'],
                    ],
                ],
                [
                    'key' => 'workshop',
                    'title' => $titles['workshop'],
                    'units' => [
                        ['key' => 'research-methods-2-workshop', 'title' => 'روش تحقیق ۲'],
                    ],
                ],
            ],
        ],
        [
            'number' => 8,
            'label' => 'ترم ۸',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        ['key' => 'endo-theory-2', 'title' => 'اندو نظری ۲'],
                        ['key' => 'advanced-prosthodontics-theory-1', 'title' => 'پروتز پیشرفته نظری ۱'],
                        ['key' => 'systemic-diseases-2', 'title' => 'بیماری‌های سیستمیک ۲'],
                        ['key' => 'diagnostics-4', 'title' => 'تشخیصی ۴'],
                        ['key' => 'perio-theory-2', 'title' => 'پریو نظری ۲'],
                        ['key' => 'ortho-theory-2', 'title' => 'ارتو نظری ۲'],
                        ['key' => 'fixed-prosthesis-foundations', 'title' => 'مبانی پروتز ثابت'],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'fixed-prosthesis-preclinic', 'title' => 'پری‌کلینیک پروتز ثابت'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'disease-practical-2', 'title' => 'بیماری عملی ۲'],
                        ['key' => 'partial-practical-2', 'title' => 'پارسیل عملی ۲'],
                        ['key' => 'perio-practical-1', 'title' => 'پریو عملی ۱'],
                        ['key' => 'ortho-practical-1', 'title' => 'ارتو عملی ۱'],
                        ['key' => 'endo-practical-1', 'title' => 'اندو عملی ۱'],
                    ],
                ],
                [
                    'key' => 'workshop',
                    'title' => $titles['workshop'],
                    'units' => [
                        ['key' => 'thesis-1', 'title' => 'رساله پایان‌نامه ۱'],
                    ],
                ],
            ],
        ],
        [
            'number' => 9,
            'label' => 'ترم ۹',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        ['key' => 'radiology-theory-3', 'title' => 'رادیو نظری ۳'],
                        ['key' => 'pediatric-theory-1', 'title' => 'کودکان نظری ۱'],
                        ['key' => 'diagnostics-5', 'title' => 'تشخیصی ۵'],
                        [
                            'key' => 'complete-edentulism-treatment',
                            'title' => 'درمان بیماران با بی‌دندانی کامل',
                            'examCourseSlugs' => ['zarb-complete-prosthodontics'],
                        ],
                        [
                            'key' => 'applied-dental-materials-theory',
                            'title' => 'مواد دندانی کاربردی',
                            'examCourseSlugs' => ['craig-dental-materials', 'vannoort-dental-materials'],
                        ],
                        ['key' => 'ortho-theory-3', 'title' => 'ارتو نظری ۳'],
                    ],
                ],
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'pediatric-preclinic', 'title' => 'پری‌کلینیک کودکان'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'radiology-practical-3', 'title' => 'رادیو عملی ۳'],
                        ['key' => 'complete-prosthesis-practical-2', 'title' => 'پروتز کامل عملی ۲'],
                        ['key' => 'endo-practical-2', 'title' => 'اندو عملی ۲'],
                        ['key' => 'fixed-practical-1', 'title' => 'پروتز ثابت عملی ۱'],
                        ['key' => 'ortho-practical-2', 'title' => 'ارتو عملی ۲'],
                        ['key' => 'perio-practical-2', 'title' => 'پریو عملی ۲'],
                        ['key' => 'rotary-endo', 'title' => 'روتاری اندو'],
                    ],
                ],
                [
                    'key' => 'workshop',
                    'title' => $titles['workshop'],
                    'units' => [
                        [
                            'key' => 'applied-dental-materials-workshop',
                            'title' => 'مواد دندانی کاربردی',
                            'examCourseSlugs' => ['craig-dental-materials', 'vannoort-dental-materials'],
                        ],
                        ['key' => 'thesis-2', 'title' => 'رساله پایان‌نامه ۲'],
                    ],
                ],
            ],
        ],
        [
            'number' => 10,
            'label' => 'ترم ۱۰',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        ['key' => 'oral-maxillofacial-anomalies', 'title' => 'ناهنجاری‌های دهان و فک و صورت'],
                        ['key' => 'pediatric-theory-2', 'title' => 'کودکان نظری ۲'],
                        ['key' => 'surgery-theory-2', 'title' => 'جراحی نظری ۲'],
                        ['key' => 'psychiatric-disorders', 'title' => 'بیماری‌های روانی'],
                        ['key' => 'perio-theory-3', 'title' => 'پریو نظری ۳'],
                        ['key' => 'advanced-prosthodontics-theory-2', 'title' => 'پروتز پیشرفته نظری ۲'],
                        ['key' => 'quality-management-clinical-excellence', 'title' => 'مدیریت کیفیت و تعالی خدمات بالینی'],
                        ['key' => 'restorative-theory-2', 'title' => 'ترمیمی نظری ۲'],
                        ['key' => 'pain-pharmacology', 'title' => 'درد و داروشناسی'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'pediatric-practical-2', 'title' => 'کودکان عملی ۲'],
                        ['key' => 'fixed-practical-2', 'title' => 'ثابت عملی ۲'],
                        ['key' => 'endo-practical-3', 'title' => 'اندو عملی ۳'],
                        ['key' => 'surgery-practical-3', 'title' => 'جراحی عملی ۳'],
                        ['key' => 'restorative-practical-3', 'title' => 'ترمیمی عملی ۳'],
                        ['key' => 'ortho-practical-3', 'title' => 'ارتو عملی ۳'],
                        ['key' => 'perio-practical-3', 'title' => 'پریو عملی ۳'],
                        ['key' => 'trauma-practical-2', 'title' => 'آسیب عملی ۲'],
                        ['key' => 'surgery-restorative-long-3', 'title' => 'جراحی و ترمیمی عملی ۳ لانگ'],
                    ],
                ],
            ],
        ],
        [
            'number' => 11,
            'label' => 'ترم ۱۱',
            'categories' => [
                [
                    'key' => 'theory',
                    'title' => $titles['theory'],
                    'units' => [
                        ['key' => 'traumatology', 'title' => 'تروماتولوژی'],
                        ['key' => 'tmj-occlusion-theory', 'title' => 'مفصل گیجگاهی فکی و اکلوژن'],
                        ['key' => 'implant-theory', 'title' => 'ایمپلنت نظری'],
                        ['key' => 'gerontology-term-11', 'title' => 'سالمندشناسی'],
                        ['key' => 'scientific-writing', 'title' => 'نگارش علمی'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'disease-practical-3', 'title' => 'بیماری عملی ۳'],
                        ['key' => 'surgery-practical-4', 'title' => 'جراحی عملی ۴'],
                        ['key' => 'perio-practical-4', 'title' => 'پریو عملی ۴'],
                        ['key' => 'ortho-practical-4', 'title' => 'ارتو عملی ۴'],
                        ['key' => 'pediatric-practical-3', 'title' => 'کودکان عملی ۳'],
                        ['key' => 'systemic-3', 'title' => 'سیستمیک ۳'],
                        ['key' => 'oral-health-practical-4', 'title' => 'سلامت عملی ۴'],
                        ['key' => 'comprehensive-treatment-1', 'title' => 'درمان جامع ۱'],
                        ['key' => 'tmj-occlusion-practical', 'title' => 'مفصل گیجگاهی فکی و اکلوژن'],
                    ],
                ],
            ],
        ],
        [
            'number' => 12,
            'label' => 'ترم ۱۲',
            'categories' => [
                [
                    'key' => 'preclinic',
                    'title' => $titles['preclinic'],
                    'units' => [
                        ['key' => 'bleaching-preclinic', 'title' => 'پری‌کلینیک بلیچینگ'],
                    ],
                ],
                [
                    'key' => 'practical',
                    'title' => $titles['practical'],
                    'units' => [
                        ['key' => 'implant-practical', 'title' => 'ایمپلنت عملی'],
                        ['key' => 'advanced-prosthodontics-practical', 'title' => 'پروتز پیشرفته عملی'],
                        ['key' => 'systemic-4', 'title' => 'سیستمیک ۴'],
                        ['key' => 'comprehensive-treatment-2', 'title' => 'درمان جامع ۲'],
                        ['key' => 'bleaching', 'title' => 'بلیچینگ'],
                    ],
                ],
            ],
        ],
    ];

    return $terms;
}

function dent_dentistry_curriculum_unit_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (dent_dentistry_curriculum_terms() as $term) {
        $termNumber = max(0, (int) ($term['number'] ?? 0));
        $termLabel = (string) ($term['label'] ?? '');
        foreach (($term['categories'] ?? []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $categoryKey = trim(strtolower((string) ($category['key'] ?? '')));
            $categoryTitle = (string) ($category['title'] ?? '');
            foreach (($category['units'] ?? []) as $unit) {
                if (!is_array($unit)) {
                    continue;
                }

                $unitKey = trim(strtolower((string) ($unit['key'] ?? '')));
                if ($unitKey === '') {
                    continue;
                }

                $normalizedUnit = $unit;
                $normalizedUnit['key'] = $unitKey;
                $normalizedUnit['termNumber'] = $termNumber;
                $normalizedUnit['termLabel'] = $termLabel;
                $normalizedUnit['categoryKey'] = $categoryKey;
                $normalizedUnit['categoryTitle'] = $categoryTitle;
                $normalizedUnit['aliases'] = array_values(array_filter(
                    is_array($unit['aliases'] ?? null) ? $unit['aliases'] : [],
                    static function ($value): bool {
                        return is_string($value) && trim($value) !== '';
                    }
                ));
                $normalizedUnit['resourceAliases'] = array_values(array_filter(
                    is_array($unit['resourceAliases'] ?? null) ? $unit['resourceAliases'] : [],
                    static function ($value): bool {
                        return is_string($value) && trim($value) !== '';
                    }
                ));
                $normalizedUnit['examCourseSlugs'] = array_values(array_filter(
                    is_array($unit['examCourseSlugs'] ?? null) ? $unit['examCourseSlugs'] : [],
                    static function ($value): bool {
                        return is_string($value) && trim($value) !== '';
                    }
                ));
                $normalizedUnit['finalExamKeys'] = array_values(array_filter(
                    is_array($unit['finalExamKeys'] ?? null) ? $unit['finalExamKeys'] : [],
                    static function ($value): bool {
                        return is_string($value) && trim($value) !== '';
                    }
                ));

                $index[$unitKey] = $normalizedUnit;
            }
        }
    }

    return $index;
}

function dent_dentistry_curriculum_find_unit(string $unitKey): ?array
{
    $cleanKey = trim(strtolower($unitKey));
    if ($cleanKey === '') {
        return null;
    }

    $index = dent_dentistry_curriculum_unit_index();
    $unit = $index[$cleanKey] ?? null;
    return is_array($unit) ? $unit : null;
}

function dent_dentistry_curriculum_exam_course_unit_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (dent_dentistry_curriculum_unit_index() as $unit) {
        if (!is_array($unit)) {
            continue;
        }

        foreach (($unit['examCourseSlugs'] ?? []) as $courseSlug) {
            $cleanSlug = trim(strtolower((string) $courseSlug));
            if ($cleanSlug === '') {
                continue;
            }

            $cleanSlug = preg_replace('/[^a-z0-9_-]+/', '', $cleanSlug) ?? '';
            if ($cleanSlug === '') {
                continue;
            }

            $index[$cleanSlug] = $unit;
        }
    }

    return $index;
}

function dent_dentistry_curriculum_find_unit_by_exam_course_slug(string $courseSlug): ?array
{
    $cleanSlug = trim(strtolower($courseSlug));
    if ($cleanSlug === '') {
        return null;
    }

    $cleanSlug = preg_replace('/[^a-z0-9_-]+/', '', $cleanSlug) ?? '';
    if ($cleanSlug === '') {
        return null;
    }

    $index = dent_dentistry_curriculum_exam_course_unit_index();
    $unit = $index[$cleanSlug] ?? null;
    return is_array($unit) ? $unit : null;
}
