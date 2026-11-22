<?php

namespace App\Reports\Extraction\Pdf;

/**
 * General-ledger "Trial Balance" (Fandaqah FIN-040 layout, English or Arabic):
 *   Account No.  Account Name  Facility Ledger ID  Debit  Credit  Debit Balance  Credit Balance
 * with section and grand totals. Chart pages (largest balances, debits by type) are skipped:
 * only lines with an account number and exactly four amounts are accounts.
 */
class LedgerTrialBalanceReader implements PdfReportReader
{
    public const TITLE = '/trial\s+balance|ميزان\s+المراجعة/iu';

    public function key(): string
    {
        return 'trial_balance';
    }

    public function matches(PdfText $text): bool
    {
        return $text->has(self::TITLE) && $text->has('/account\s+no\.?|account\s+name|رقم\s+الحساب|اسم\s+الحساب/iu');
    }

    public function read(PdfText $text): array
    {
        $out = ReaderHeader::make($text, 'trial_balance', self::TITLE);

        if (preg_match('/(?:period|الفترة)\s+('.Text::DATE.')\s*-\s*('.Text::DATE.')/iu', $text->all(), $m)) {
            $dates = array_filter([Text::date($m[1]), Text::date($m[2])]);
            sort($dates);
            $out['period_start'] = $dates[0] ?? null;
            $out['business_date'] = end($dates) ?: null;
        } else {
            $out['business_date'] = Text::date($text->find('/(?:as\s+of|date|التاريخ)\s+('.Text::DATE.')/iu'));
        }

        $lines = [];
        $totalDebit = null;
        $totalCredit = null;

        foreach ($text->lines() as $line) {
            $cells = array_column(Text::cells($line), 'text');
            if ($cells === []) {
                continue;
            }
            // Arabic pages are mirrored: amounts first, account number last.
            if (Text::isNumber($cells[0]) && str_contains($cells[0], '.') && ! Text::isNumber(end($cells))
                || (preg_match('/^\d{3,}$/', end($cells)) && Text::isNumber($cells[0]) && str_contains($cells[0], '.'))) {
                $cells = array_reverse($cells);
            }

            $amounts = [];
            while ($cells !== [] && Text::isNumber(end($cells)) && str_contains(end($cells), '.')) {
                array_unshift($amounts, Text::number(array_pop($cells)));
            }
            if (count($amounts) !== 4 || $cells === []) {
                continue;
            }

            $label = implode(' ', $cells);
            if (in_array(Text::key($label), [Text::key('Grand Total'), Text::key('الإجمالي العام')], true)) {
                [$totalDebit, $totalCredit] = [$amounts[0], $amounts[1]];

                continue;
            }

            if (! preg_match('/^\d{3,}$/', $cells[0])) {
                continue;   // section total or chart line
            }

            $code = array_shift($cells);
            $local = $cells !== [] && preg_match('/^\d{3,}$/', end($cells)) ? array_pop($cells) : null;
            $lines[] = [
                'account_code' => $code,
                'local_account_code' => $local,
                'account_name' => implode(' ', $cells),
                'account_class' => null,
                'opening_balance' => null,
                'debit' => $amounts[0],
                'credit' => $amounts[1],
                'closing_balance' => round($amounts[2] - $amounts[3], 2),
            ];
        }

        if ($lines === []) {
            $out['issues'][] = 'No account lines were recognised on this report.';
        }

        $out['trial_balance'] = ['lines' => $lines, 'printed_total_debit' => $totalDebit, 'printed_total_credit' => $totalCredit];

        return $out;
    }
}
