<?php

namespace App\Enums;

enum ReportType: string
{
    /** Daily KPIs: rooms, occupancy, revenue (Fandaqah EOD-016, Opera Manager Report). */
    case ManagerFlash = 'manager_flash';

    /** General-ledger trial balance: accounts with debit/credit (Fandaqah FST-001 / FAC-001). */
    case TrialBalance = 'trial_balance';

    /** Front-office trial balance: guest / AR / deposit ledger roll-forward (Opera trial_balance, Fandaqah FIN-040). */
    case FrontOfficeTrialBalance = 'front_office_trial_balance';

    /** Guest trial balance: one line per guest / company folio with opening, debits, credits, closing (Fandaqah EOD-010, Opera gl_trial_balance). */
    case GuestTrialBalance = 'guest_trial_balance';

    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ManagerFlash => 'Manager Flash Report',
            self::TrialBalance => 'Trial Balance (General Ledger)',
            self::FrontOfficeTrialBalance => 'Trial Balance (Front Office)',
            self::GuestTrialBalance => 'Guest Trial Balance',
            self::Unknown => 'Unknown',
        };
    }
}
