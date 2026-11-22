<?php

namespace App\Reports\Extraction\Pdf;

/** The canonical header every reader starts from. */
final class ReaderHeader
{
    /** @return array<string, mixed> */
    public static function make(PdfText $text, string $type, string $titlePattern): array
    {
        return [
            'report_type' => $type,
            'report_title_as_printed' => $text->title($titlePattern),
            'property_name' => $text->propertyName($titlePattern),
            'property_code' => null,
            'period_start' => null,
            'business_date' => null,
            'provisional' => $text->provisional(),
            'currency' => null,
            'confidence' => 'high',
            'issues' => [],
            'manager_flash' => null,
            'trial_balance' => null,
            'front_office_trial_balance' => null,
            'guest_trial_balance' => null,
        ];
    }
}
