<?php

namespace Tests\Unit;

use App\Reports\DocumentHints;
use PHPUnit\Framework\TestCase;

class DocumentHintsTest extends TestCase
{
    public function test_fandaqah_subject_and_file_name_give_the_business_date(): void
    {
        $hints = new DocumentHints(subject: 'Manager Flash – فندق العنوان – 2026-11-19', filename: 'manager_flash_20261119.pdf');

        $this->assertSame('2026-11-19', $hints->statedDate());
    }

    public function test_conflicting_or_period_dates_are_ambiguous(): void
    {
        $this->assertNull((new DocumentHints(subject: 'Flash 2026-11-19', filename: 'flash_20261118.pdf'))->statedDate());
        $this->assertNull((new DocumentHints(filename: 'FST-001_address_2026-11-01_2026-11-19.pdf'))->statedDate());
    }

    public function test_no_date_and_invalid_dates_give_null(): void
    {
        $this->assertNull((new DocumentHints(subject: 'Daily report'))->statedDate());
        $this->assertNull((new DocumentHints(filename: 'report_20261340.pdf'))->statedDate());
    }
}
