<?php

namespace App\Reports\Extraction\Pdf;

/**
 * "Guest Trial Balance" (program gl_trial_balance, EOD-010), Opera / Fandaqah layout:
 * one line per guest or company folio:
 *   Room  Name  Arr. Date  Dep. Date  Opening  Debits  Transfer Debit  Credits  Transfer Credit  Closing  Folio No.  Status
 * A name can wrap onto the next line; folio numbers can wrap too (ignored). The final
 * "Total" line is kept so the validator can check every folio against it.
 */
class GuestTrialBalanceReader implements PdfReportReader
{
    public const TITLE = '/guest\s+trial\s+balance|ميزان\s+(?:مراجعة\s+)?النزلاء/iu';

    public function key(): string
    {
        return 'guest_trial_balance';
    }

    public function matches(PdfText $text): bool
    {
        return $text->has(self::TITLE) || $text->has('/\bgl_trial_balance\b/');
    }

    public function read(PdfText $text): array
    {
        $out = ReaderHeader::make($text, 'guest_trial_balance', self::TITLE);
        $date = $text->find('/(?:for\s+date|للتاريخ|بتاريخ)\s*('.Text::DATE.')/iu');
        $out['business_date'] = Text::date($date) ?? $text->printDate();
        if ($date === null) {
            $out['issues'][] = 'No "For Date" found: the business date is the print date.';
        }

        $n = Text::NUMBER;
        $d = Text::DATE;
        $folio = "/^(?:(?<room>\S+)\s+)?\s*(?<name>.*?)\s+(?<arr>{$d})\s+(?<dep>{$d})\s+(?<v1>{$n})\s+(?<v2>{$n})\s+(?<v3>{$n})\s+(?<v4>{$n})\s+(?<v5>{$n})\s+(?<v6>{$n})(?<rest>.*)$/u";
        $total = "/^\s*total\s+(?<v1>{$n})\s+(?<v2>{$n})\s+(?<v3>{$n})\s+(?<v4>{$n})\s+(?<v5>{$n})\s+(?<v6>{$n})\s*$/iu";

        $lines = [];
        $totals = null;
        $previousWasFolio = false;
        $inferred = 0;

        foreach ($text->lines() as $line) {
            if (preg_match($total, $line, $m)) {
                $totals = $this->amounts($m);
                $previousWasFolio = false;

                continue;
            }

            if (preg_match($folio, $line, $m)) {
                $room = $m['room'] !== '' ? $m['room'] : null;
                $status = $this->status($m['rest'], Text::date($m['arr']), Text::date($m['dep']), $out['business_date'], $printed);
                $inferred += $printed ? 0 : 1;
                $lines[] = [
                    'room' => $room,
                    'name' => trim($m['name']) !== '' ? trim($m['name']) : null,
                    'arrival' => Text::date($m['arr']),
                    'departure' => Text::date($m['dep']),
                    ...$this->amounts($m),
                    'status' => $status,
                ];
                $previousWasFolio = true;

                continue;
            }

            // A wrapped guest name: indented, letters only, right after a folio line.
            $trimmed = trim($line);
            if ($previousWasFolio && $lines !== [] && preg_match('/^\s+\S/u', $line) && preg_match('/^[\p{L}][\p{L}\s,.\'\-()&\/]*$/u', $trimmed)
                && ! preg_match('/^(room types|sort order|include|filter|subtotal|total|reservations)\b/iu', $trimmed)) {
                $last = array_key_last($lines);
                $lines[$last]['name'] = trim(($lines[$last]['name'] ?? '').' '.$trimmed);

                continue;
            }

            if ($trimmed !== '' && ! preg_match('/^[\d,\s]+$/', $trimmed)) {
                $previousWasFolio = false;   // folio-number continuation lines keep the folio open
            }
        }

        if ($inferred > 0) {
            $out['issues'][] = "Status not printed for {$inferred} folio(s): taken from the arrival / departure dates.";
        }
        if ($lines === []) {
            $out['issues'][] = 'No folio lines were recognised on this report.';
        }

        $out['guest_trial_balance'] = ['lines' => $lines, 'printed_totals' => $totals];

        return $out;
    }

    /** @return array<string, float> */
    private function amounts(array $m): array
    {
        return [
            'opening_balance' => Text::number($m['v1']),
            'debit' => Text::number($m['v2']),
            'transfer_debit' => Text::number($m['v3']),
            'credit' => Text::number($m['v4']),
            'transfer_credit' => Text::number($m['v5']),
            'closing_balance' => Text::number($m['v6']),
        ];
    }

    /**
     * CKIN → in house, CKOT → checked out. When the status column is cut off or blank, use
     * the dates: a future arrival is a reservation (other), a past departure is checked out.
     */
    private function status(string $rest, ?string $arrival, ?string $departure, ?string $date, ?bool &$printed): string
    {
        $printed = true;
        if (preg_match('/\b(CKI\w*|IN\s?HOUSE|CHECKED\s+IN)\s*$/iu', $rest)) {
            return 'in_house';
        }
        if (preg_match('/\b(CKO\w*|CHECKED\s+OUT)\s*$/iu', $rest)) {
            return 'checked_out';
        }

        $printed = false;
        if ($date === null) {
            return 'other';
        }

        return match (true) {
            $arrival !== null && $arrival > $date => 'other',
            $departure !== null && $departure < $date => 'checked_out',
            default => 'in_house',
        };
    }
}
