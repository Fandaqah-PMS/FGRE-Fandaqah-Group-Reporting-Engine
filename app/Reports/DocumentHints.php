<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * What the email around a report says about it: sender, subject, file name,
 * and the business date found in them (e.g. Fandaqah: subject
 * "Manager Flash – {hotel} – 2026-11-19", file "manager_flash_20261119.pdf").
 * Used to cross-check what the AI read from the PDF.
 */
final readonly class DocumentHints
{
    public function __construct(
        public ?string $fromEmail = null,
        public ?string $subject = null,
        public ?string $filename = null,
        public ?int $propertyId = null,
    ) {}

    /** The single business date stated in subject / file name; null when none or ambiguous. */
    public function statedDate(): ?string
    {
        $dates = [];
        foreach ([$this->subject, $this->filename] as $text) {
            if ($text === null) {
                continue;
            }

            preg_match_all('/(?<!\d)(20\d{2})-(\d{2})-(\d{2})(?!\d)/', $text, $iso, PREG_SET_ORDER);
            preg_match_all('/(?<!\d)(20\d{2})(\d{2})(\d{2})(?!\d)/', $text, $compact, PREG_SET_ORDER);

            foreach ([...$iso, ...$compact] as [, $y, $m, $d]) {
                if (checkdate((int) $m, (int) $d, (int) $y)) {
                    $dates[] = CarbonImmutable::create((int) $y, (int) $m, (int) $d)->toDateString();
                }
            }
        }

        $dates = array_values(array_unique($dates));

        return count($dates) === 1 ? $dates[0] : null;
    }
}
