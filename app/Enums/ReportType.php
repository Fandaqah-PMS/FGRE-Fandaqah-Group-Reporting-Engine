<?php

namespace App\Enums;

enum ReportType: string
{
    case ManagerFlash = 'manager_flash';
    case TrialBalance = 'trial_balance';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ManagerFlash => 'Manager Flash Report',
            self::TrialBalance => 'Trial Balance',
            self::Unknown => 'Unknown',
        };
    }
}
