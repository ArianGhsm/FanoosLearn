<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

use DateTimeImmutable;

/**
 * The Persian (Jalali) calendar, for what students count in it: "this month"
 * is مهر, not October. Arithmetic only (the 33-year cycle used by every
 * Iranian calendar library), so it does not depend on the intl extension.
 */
final class JalaliCalendar
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** @return array{0:int,1:int,2:int} [year, month 1-12, day] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $daysBeforeMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $daysBeforeMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + ($days % 31)];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + (($days - 186) % 30)];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function of(DateTimeImmutable $date): array
    {
        return self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    /** The first day of the Jalali month that $date falls in, at the same time of day. */
    public static function monthStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $day = self::of($date)[2];

        return $day === 1 ? $date : $date->modify('-' . ($day - 1) . ' days');
    }

    /** The Jalali month name of $date ("مهر"). */
    public static function monthName(DateTimeImmutable $date): string
    {
        return self::MONTHS[self::of($date)[1] - 1];
    }
}
