<?php
declare(strict_types=1);

/**
 * Metadata extracted from the supplied "مبانی پروتز پارسیل نظری (ترم ۷)"
 * course syllabus PDF. This file does not define a timetable; it only enriches
 * the existing canonical partial-basics-theory occurrence by Jalali date.
 */
const CLASSOPS_PARTIAL_THEORY_SYLLABUS_VERSION = '1405-1406-1.pdf.1';

function classops_partial_theory_syllabus(): array
{
    static $syllabus = null;
    if (is_array($syllabus)) {
        return $syllabus;
    }

    $syllabus = [
        'version' => CLASSOPS_PARTIAL_THEORY_SYLLABUS_VERSION,
        'courseSlug' => 'partial-basics-theory',
        'courseTitle' => 'مبانی پارسیل نظری',
        'sourceCourseTitle' => 'مبانی پروتز پارسیل نظری (ترم ۷)',
        'semester' => '1405-1406-1',
        'courseCoordinator' => 'دکتر سیاوش اسدی',
        'sessions' => [
            ['sessionNumber' => 1, 'sourcePage' => 1, 'sourceDate' => '05/07/01', 'jalaliDate' => '1405/07/01', 'dateAmbiguous' => false, 'title' => 'اپیدمیولوژی، فیزیولوژی و واژه‌شناسی؛ طبقه‌بندی قوس‌های بی‌دندانی', 'instructor' => 'دکتر بهرامی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۱'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 2, 'sourcePage' => 1, 'sourceDate' => '05/07/08', 'jalaliDate' => '1405/07/08', 'dateAmbiguous' => false, 'title' => 'پروتز پارسیلی که با کالسپ نگهداری می‌شود', 'instructor' => 'دکتر جوکار', 'references' => ['مک‌کراکن ۲۰۱۶، فصل‌های ۲ و ۳'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 3, 'sourcePage' => 1, 'sourceDate' => '054/07/15', 'jalaliDate' => null, 'dateAmbiguous' => true, 'title' => 'اتصال‌دهنده اصلی فک بالا و اتصال‌دهنده فرعی', 'instructor' => 'دکتر اسدی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۵'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 4, 'sourcePage' => 1, 'sourceDate' => '05/07/22', 'jalaliDate' => '1405/07/22', 'dateAmbiguous' => false, 'title' => 'اتصال‌دهنده اصلی فک پایین و اتصال‌دهنده فرعی', 'instructor' => 'دکتر اسدی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۵'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 5, 'sourcePage' => 1, 'sourceDate' => '05/07/29', 'jalaliDate' => '1405/07/29', 'dateAmbiguous' => false, 'title' => 'تشخیص و طرح درمان', 'instructor' => 'دکتر اسدی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۱۳'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 6, 'sourcePage' => 1, 'sourceDate' => '05/08/06', 'jalaliDate' => '1405/08/06', 'dateAmbiguous' => false, 'title' => 'سرویور و اعمال آن', 'instructor' => 'دکتر حاجی محمودی', 'references' => ['استوارت ۲۰۰۸، فصل ۷', 'مک‌کراکن ۲۰۱۶، فصل ۱۱'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 7, 'sourcePage' => 1, 'sourceDate' => '05/08/13', 'jalaliDate' => '1405/08/13', 'dateAmbiguous' => false, 'title' => 'بیومکانیک پروتز پارسیل متحرک', 'instructor' => 'دکتر حاجی محمودی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۴'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 8, 'sourcePage' => 1, 'sourceDate' => '05/08/27', 'jalaliDate' => '1405/08/27', 'dateAmbiguous' => false, 'title' => 'نگهدارنده مستقیم (۱)', 'instructor' => 'دکتر حاجی محمودی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۷'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 9, 'sourcePage' => 1, 'sourceDate' => '05/09/04', 'jalaliDate' => '1405/09/04', 'dateAmbiguous' => false, 'title' => 'نگهدارنده مستقیم (۲)', 'instructor' => 'دکتر حاجی محمودی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۷'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 10, 'sourcePage' => 2, 'sourceDate' => '05/09/04', 'jalaliDate' => '1405/09/04', 'dateAmbiguous' => false, 'title' => 'ملاحظات بیس پروتز', 'instructor' => 'دکتر عطری', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۹'], 'sessionMode' => 'virtual', 'sessionModeLabel' => 'مجازی'],
            ['sessionNumber' => 11, 'sourcePage' => 2, 'sourceDate' => '05/09/04', 'jalaliDate' => '1405/09/04', 'dateAmbiguous' => false, 'title' => 'آماده‌سازی دهان برای پروتز پارسیل', 'instructor' => 'دکتر عطری', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۱۴'], 'sessionMode' => 'virtual', 'sessionModeLabel' => 'مجازی'],
            ['sessionNumber' => 12, 'sourcePage' => 2, 'sourceDate' => '05/09/11', 'jalaliDate' => '1405/09/11', 'dateAmbiguous' => false, 'title' => 'نگهدارنده غیرمستقیم', 'instructor' => 'دکتر حاجی محمودی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۸'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 13, 'sourcePage' => 2, 'sourceDate' => '05/09/18', 'jalaliDate' => '1405/09/18', 'dateAmbiguous' => false, 'title' => 'رست و جایگاه رست', 'instructor' => 'دکتر بهرامی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۶'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 14, 'sourcePage' => 2, 'sourceDate' => '05/09/25', 'jalaliDate' => '1405/09/25', 'dateAmbiguous' => false, 'title' => 'آماده‌سازی دندان پایه', 'instructor' => 'دکتر مصطفوی', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۱۵'], 'sessionMode' => 'in_person', 'sessionModeLabel' => 'حضوری'],
            ['sessionNumber' => 15, 'sourcePage' => 2, 'sourceDate' => '05/09/25', 'jalaliDate' => '1405/09/25', 'dateAmbiguous' => false, 'title' => 'مواد و روش‌های قالبگیری (اولیه و نهایی) در پروتز پارسیل متحرک', 'instructor' => 'دکتر جوکار', 'references' => ['مک‌کراکن ۲۰۱۶، فصل ۱۶'], 'sessionMode' => 'virtual', 'sessionModeLabel' => 'مجازی'],
        ],
    ];

    return $syllabus;
}

function classops_partial_theory_sessions_for_date(string $jalaliDate): array
{
    $sessions = array_values(array_filter(
        classops_partial_theory_syllabus()['sessions'],
        static fn(array $session): bool => (string) ($session['jalaliDate'] ?? '') === $jalaliDate
    ));
    usort($sessions, static fn(array $left, array $right): int => (int) ($left['sessionNumber'] ?? 0) <=> (int) ($right['sessionNumber'] ?? 0));
    return $sessions;
}

function classops_partial_theory_enrich_events(array $events, string $jalaliDate): array
{
    $out = [];
    foreach ($events as $event) {
        if (!is_array($event) || (string) ($event['slug'] ?? '') !== 'partial-basics-theory') {
            if (is_array($event)) {
                $out[] = $event;
            }
            continue;
        }

        $sessions = classops_partial_theory_sessions_for_date($jalaliDate);
        if ($sessions === []) {
            $out[] = $event;
            continue;
        }

        foreach ($sessions as $session) {
            $copy = $event;
            $copy['courseTitle'] = (string) ($event['title'] ?? 'مبانی پارسیل نظری');
            $copy['sessionNumber'] = (int) ($session['sessionNumber'] ?? 0);
            $copy['sessionTitle'] = (string) ($session['title'] ?? '');
            $copy['instructor'] = (string) ($session['instructor'] ?? '');
            $copy['references'] = is_array($session['references'] ?? null) ? $session['references'] : [];
            $copy['sessionMode'] = (string) ($session['sessionMode'] ?? 'in_person');
            $copy['sessionModeLabel'] = (string) ($session['sessionModeLabel'] ?? '');
            $copy['sourceDate'] = (string) ($session['sourceDate'] ?? '');
            $copy['sourcePage'] = (int) ($session['sourcePage'] ?? 0);
            $copy['sessionSource'] = 'partial-theory-course-syllabus';
            $modeSuffix = $copy['sessionMode'] === 'virtual' ? ' · مجازی' : '';
            $copy['title'] = $copy['courseTitle'] . ' — جلسه ' . $copy['sessionNumber'] . ': ' . $copy['sessionTitle'] . $modeSuffix;
            if ($copy['sessionMode'] === 'virtual') {
                // The course table gives the canonical Wednesday time, but the
                // virtual rows must not imply the physical amphitheatre.
                $copy['location'] = '';
            }
            $out[] = $copy;
        }
    }
    return $out;
}
