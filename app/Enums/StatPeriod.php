<?php

namespace App\Enums;

enum StatPeriod: string
{
    case Day = 'day';
    case MonthToDate = 'mtd';
    case YearToDate = 'ytd';
}
