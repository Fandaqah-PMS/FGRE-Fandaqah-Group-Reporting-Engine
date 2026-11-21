<?php

namespace App\Enums;

/**
 * Columns of a manager flash report. "_ly" = same period last year
 * (same calendar date; 29 Feb maps to 28 Feb).
 */
enum StatPeriod: string
{
    case Day = 'day';
    case MonthToDate = 'mtd';
    case YearToDate = 'ytd';
    case DayLastYear = 'day_ly';
    case MonthToDateLastYear = 'mtd_ly';
    case YearToDateLastYear = 'ytd_ly';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    public function isLastYear(): bool
    {
        return str_ends_with($this->value, '_ly');
    }
}
