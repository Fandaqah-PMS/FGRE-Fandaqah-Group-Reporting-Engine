<?php

namespace App\Reports\Extraction\Pdf;

/**
 * Night-audit "Trial Balance" (program trial_balance), Opera / Fandaqah layout, English or
 * Arabic: a "Daily Transactions" block (Revenue, Non Revenue, Paid Out, Payment groups with
 * their totals) followed by ledger roll-forwards (Guest, AR / City, Deposit, Package Ledger:
 * Balance Yesterday, movements, Balance Today) and the Hotel Balance.
 */
class FrontOfficeTrialBalanceReader implements PdfReportReader
{
    public const TITLE = '/trial\s+balance|ميزان\s+المراجعة/iu';

    /** Group headings (no amount on the line) inside Daily Transactions. */
    private const GROUPS = [
        'revenue' => 'revenue', 'الإيرادات' => 'revenue',
        'non revenue' => 'non_revenue', 'غير الإيرادات' => 'non_revenue',
        'paid out' => 'paid_out', 'paid outs' => 'paid_out', 'المدفوعات' => 'paid_out',
        'payment' => 'payment', 'payments' => 'payment', 'المقبوضات' => 'payment',
    ];

    /** Ledger headings (no amount on the line). */
    private const LEDGERS = [
        'guest ledger' => 'guest', 'النزلاء' => 'guest', 'دفتر النزلاء' => 'guest',
        'ar ledger' => 'ar', 'a/r ledger' => 'ar', 'city ledger' => 'ar', 'الذمم المدينة' => 'ar', 'دفتر الذمم المدينة' => 'ar',
        'deposit ledger' => 'deposit', 'العربون' => 'deposit', 'دفتر العربون' => 'deposit',
        'package ledger' => 'package', 'الباقات' => 'package', 'دفتر الباقات' => 'package',
    ];

    public function key(): string
    {
        return 'front_office_trial_balance';
    }

    public function matches(PdfText $text): bool
    {
        return $text->has(self::TITLE)
            && $text->has('/balance\s+yesterday|guest\s+ledger|daily\s+transactions|رصيد\s+الأمس|الحركات\s+اليومية/iu');
    }

    public function read(PdfText $text): array
    {
        $out = ReaderHeader::make($text, 'front_office_trial_balance', self::TITLE);
        $out['business_date'] = Text::date($text->find('/(?:filter\s+date|(?<![\/\p{L}])date|(?<![\/\p{L}])التاريخ)\s+('.Text::DATE.')/iu'));

        $groups = $this->keyed(self::GROUPS);
        $ledgerKeys = $this->keyed(self::LEDGERS);

        $transactions = [];
        $categoryTotals = [];
        $ledgers = [];
        $hotelBalance = null;
        $group = null;
        $ledger = null;

        foreach ($text->lines() as $line) {
            $row = Text::row($line);
            $key = Text::key($row['label']);
            if ($key === '') {
                continue;
            }

            if ($row['numbers'] === []) {
                if (isset($ledgerKeys[$key])) {
                    $ledger = $ledgerKeys[$key];
                    $group = null;
                    $ledgers[$ledger] ??= ['ledger' => $ledger, 'label_as_printed' => $row['label'], 'opening_balance' => null, 'movements' => [], 'closing_balance' => null];
                } elseif ($ledger === null && isset($groups[$key])) {
                    $group = $groups[$key];
                }

                continue;
            }

            $amount = end($row['numbers']);

            if (preg_match('/^(hotel balance|رصد الفندق)$/u', $key)) {
                $hotelBalance = $amount;
                $ledger = null;

                continue;
            }

            if ($ledger !== null) {
                if (preg_match('/^(balance yesterday|رصد الامس)$/u', $key)) {
                    $ledgers[$ledger]['opening_balance'] = $amount;
                } elseif (preg_match('/^(balance today|رصد الوم)$/u', $key)) {
                    $ledgers[$ledger]['closing_balance'] = $amount;
                    $ledger = null;
                } elseif (! preg_match('/^(balance|الرصد)\b/u', $key)) {
                    $ledgers[$ledger]['movements'][] = ['label' => $row['label'], 'amount' => $amount];
                }

                continue;
            }

            if ($group !== null) {
                if (preg_match('/\btotal$|^اجمال/u', $key)) {
                    $categoryTotals[] = ['category' => $group, 'printed_total' => $amount];
                    $group = null;
                } else {
                    [$code, $description] = preg_match('/^(\S*\d\S*)\s+(.+)$/u', $row['label'], $m) && ! str_contains($m[1], '.') ? [$m[1], $m[2]] : [null, $row['label']];
                    $transactions[] = ['code' => $code, 'description' => $description, 'category' => $group, 'amount' => $amount];
                }
            }
        }

        $ledgers = array_values(array_filter($ledgers, fn ($l) => $l['closing_balance'] !== null));
        if ($ledgers === []) {
            $out['issues'][] = 'No ledger sections (Balance Yesterday / Balance Today) were recognised.';
        }

        $out['front_office_trial_balance'] = [
            'transactions' => $transactions,
            'category_totals' => $categoryTotals,
            'ledgers' => $ledgers,
            'printed_hotel_balance' => $hotelBalance,
        ];

        return $out;
    }

    /** @return array<string, string> */
    private function keyed(array $map): array
    {
        $out = [];
        foreach ($map as $label => $value) {
            $out[Text::key($label)] = $value;
        }

        return $out;
    }
}
