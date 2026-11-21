<?php

namespace App\Reports\Validation;

use Carbon\CarbonImmutable;

/**
 * Cross-checks extracted report data before it is stored.
 *
 * Any error sends the document to the review queue. Checks compare printed
 * values with values recomputed from other printed values, so a misread digit
 * is caught instead of silently stored. PMSs define occupancy and ADR
 * differently, so a printed ratio passes if it matches any standard variant.
 */
class ExtractionValidator
{
    private float $moneyTolerance;

    private float $percentTolerance;

    public function __construct()
    {
        $this->moneyTolerance = (float) config('fgre.validation.money_tolerance', 0.05);
        $this->percentTolerance = (float) config('fgre.validation.percent_tolerance', 0.15);
    }

    /**
     * @param  array<string, mixed>  $output  data in the ExtractionSchema shape
     * @param  ?string  $expectedDate  business date stated outside the PDF (email subject / file name)
     * @return list<string> human-readable errors; empty when valid
     */
    public function validate(array $output, ?string $expectedDate = null): array
    {
        $type = $output['report_type'] ?? 'unknown';
        if ($type === 'unknown') {
            return ['Report type could not be identified.'];
        }

        $errors = $this->validateDates($output['business_date'] ?? null, $output['period_start'] ?? null, $expectedDate);

        if (($output['confidence'] ?? 'low') === 'low') {
            $errors[] = 'AI reported low confidence: '.implode('; ', $output['issues'] ?? []);
        }

        return [...$errors, ...match ($type) {
            'manager_flash' => $this->validateFlash($output['manager_flash'] ?? null),
            'trial_balance' => $this->validateTrialBalance($output['trial_balance'] ?? null),
            'front_office_trial_balance' => $this->validateFrontOffice($output['front_office_trial_balance'] ?? null),
            default => ["Unsupported report type [{$type}]."],
        }];
    }

    /** @return list<string> */
    private function validateDates(?string $date, ?string $periodStart, ?string $expected): array
    {
        if ($date === null) {
            return ['Business date is missing.'];
        }

        $parsed = $this->parseDate($date);
        if ($parsed === null) {
            return ["Business date [{$date}] is not a valid date."];
        }

        $errors = [];
        if ($parsed->isAfter(CarbonImmutable::today()->addDay())) {
            $errors[] = "Business date [{$date}] is in the future.";
        }

        if ($periodStart !== null) {
            $start = $this->parseDate($periodStart);
            if ($start === null || $start->isAfter($parsed)) {
                $errors[] = "Period start [{$periodStart}] is not on or before the business date [{$date}].";
            }
        }

        if ($expected !== null && $expected !== $date) {
            $errors[] = "Report says business date [{$date}] but the email subject / file name says [{$expected}].";
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>|null  $flash
     * @return list<string>
     */
    private function validateFlash(?array $flash): array
    {
        if ($flash === null || empty($flash['metrics'])) {
            return ['Manager flash report has no metrics.'];
        }

        $errors = [];
        $labels = ['day' => 'Day', 'mtd' => 'MTD', 'ytd' => 'YTD', 'day_ly' => 'Day LY', 'mtd_ly' => 'MTD LY', 'ytd_ly' => 'YTD LY'];

        foreach ($labels as $period => $label) {
            $m = [];
            foreach ($flash['metrics'] as $row) {
                $m[$row['metric']] = $row[$period] ?? null;
            }

            $total = $m['total_rooms'] ?? null;
            $occ = $m['rooms_occupied'] ?? null;
            $ooo = (float) ($m['ooo_rooms'] ?? 0);
            $paid = $occ === null ? null : $occ - ($m['comp_rooms'] ?? 0) - ($m['house_use_rooms'] ?? 0);
            $roomRevenue = $m['room_revenue'] ?? null;

            if ($total !== null && $occ !== null && $occ > $total) {
                $errors[] = "{$label}: rooms occupied ({$occ}) exceed total rooms ({$total}).";
            }

            if ($total !== null && $occ !== null && ($printed = $m['occupancy_pct'] ?? null) !== null) {
                $candidates = $this->ratios([$occ, $paid], [$total, $total - $ooo], 100);
                if (! $this->matchesAny($printed, $candidates, $this->percentTolerance)) {
                    $errors[] = sprintf('%s: occupancy %.2f%% does not match rooms occupied / total rooms (%s).', $label, $printed, $this->fmt($candidates));
                }
            }

            if ($occ !== null && $roomRevenue !== null && ($printed = $m['adr'] ?? null) !== null) {
                $comp = $m['comp_rooms'] ?? 0;
                $house = $m['house_use_rooms'] ?? 0;
                $candidates = $this->ratios([$roomRevenue], [$occ, $paid, $occ - $comp, $occ - $house]);
                if (! $this->matchesAny($printed, $candidates, $this->moneyTolerance, 0.005)) {
                    $errors[] = sprintf('%s: ADR %.2f does not match room revenue / rooms occupied (%s).', $label, $printed, $this->fmt($candidates));
                }
            }

            if ($total !== null && $roomRevenue !== null && ($printed = $m['revpar'] ?? null) !== null) {
                $candidates = $this->ratios([$roomRevenue], [$total, $total - $ooo]);
                if (! $this->matchesAny($printed, $candidates, $this->moneyTolerance, 0.005)) {
                    $errors[] = sprintf('%s: RevPAR %.2f does not match room revenue / total rooms (%s).', $label, $printed, $this->fmt($candidates));
                }
            }

            // Total revenue can include more departments, but never less than its printed parts.
            $parts = [$roomRevenue, $m['fnb_revenue'] ?? null, $m['other_revenue'] ?? null];
            if (($grand = $m['total_revenue'] ?? null) !== null && ! in_array(null, $parts, true)
                && $grand < array_sum($parts) - $this->moneyTolerance) {
                $errors[] = sprintf('%s: total revenue %.2f is less than room + F&B + other (%.2f).', $label, $grand, array_sum($parts));
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>|null  $tb
     * @return list<string>
     */
    private function validateTrialBalance(?array $tb): array
    {
        if ($tb === null || empty($tb['lines'])) {
            return ['Trial balance has no account lines.'];
        }

        $errors = [];
        $debit = $credit = $closing = 0.0;
        $allClosing = true;
        $seen = [];

        foreach ($tb['lines'] as $line) {
            $d = (float) $line['debit'];
            $c = (float) $line['credit'];
            $debit += $d;
            $credit += $c;

            $key = $line['account_code'].'|'.($line['local_account_code'] ?? '');
            if (isset($seen[$key])) {
                $errors[] = "Account [{$line['account_code']}] appears more than once.";
            }
            $seen[$key] = true;

            if ($line['closing_balance'] === null) {
                $allClosing = false;
            } else {
                $closing += (float) $line['closing_balance'];
            }

            $o = $line['opening_balance'];
            if ($o !== null && $line['closing_balance'] !== null
                && ! $this->isClose($o + $d - $c, (float) $line['closing_balance'], $this->moneyTolerance)) {
                $errors[] = sprintf('Account [%s]: opening %.2f + debit %.2f - credit %.2f does not equal closing %.2f.', $line['account_code'], $o, $d, $c, $line['closing_balance']);
            }
        }

        if (! $this->isClose($debit, $credit, $this->moneyTolerance)) {
            $errors[] = sprintf('Total debit %.2f does not equal total credit %.2f.', $debit, $credit);
        }

        if ($allClosing && ! $this->isClose($closing, 0.0, $this->moneyTolerance)) {
            $errors[] = sprintf('Closing balances do not net to zero (%.2f).', $closing);
        }

        foreach (['debit' => $debit, 'credit' => $credit] as $side => $sum) {
            $printed = $tb["printed_total_{$side}"] ?? null;
            if ($printed !== null && ! $this->isClose($sum, $printed, $this->moneyTolerance)) {
                $errors[] = sprintf('Sum of %s lines %.2f does not match printed total %.2f.', $side, $sum, $printed);
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>|null  $fo
     * @return list<string>
     */
    private function validateFrontOffice(?array $fo): array
    {
        if ($fo === null || empty($fo['ledgers'])) {
            return ['Front-office trial balance has no ledger sections.'];
        }

        $errors = [];

        foreach ($fo['category_totals'] ?? [] as $total) {
            $sum = collect($fo['transactions'] ?? [])->where('category', $total['category'])->sum('amount');
            if (! $this->isClose($sum, $total['printed_total'], $this->moneyTolerance)) {
                $errors[] = sprintf('%s lines sum to %.2f but the printed total is %.2f.', ucfirst(str_replace('_', ' ', $total['category'])), $sum, $total['printed_total']);
            }
        }

        $closings = [];
        foreach ($fo['ledgers'] as $ledger) {
            $closings[$ledger['ledger']] = ($closings[$ledger['ledger']] ?? 0) + $ledger['closing_balance'];

            if ($ledger['opening_balance'] === null) {
                continue;
            }

            // Movements are signed as printed; some PMSs print payments as positive amounts.
            $signed = $ledger['opening_balance'] + array_sum(array_column($ledger['movements'], 'amount'));
            $paymentsFlipped = $ledger['opening_balance'];
            foreach ($ledger['movements'] as $mv) {
                $paymentsFlipped += str_contains(mb_strtolower($mv['label']), 'payment') ? -abs($mv['amount']) : $mv['amount'];
            }

            if (! $this->matchesAny($ledger['closing_balance'], [$signed, $paymentsFlipped], $this->moneyTolerance)) {
                $errors[] = sprintf('%s: balance yesterday %.2f + movements does not equal balance today %.2f.', $ledger['label_as_printed'], $ledger['opening_balance'], $ledger['closing_balance']);
            }
        }

        if (($hotel = $fo['printed_hotel_balance'] ?? null) !== null) {
            $main = ($closings['guest'] ?? 0) + ($closings['ar'] ?? 0) + ($closings['deposit'] ?? 0);
            if (! $this->matchesAny($hotel, [$main, $main + ($closings['package'] ?? 0)], $this->moneyTolerance)) {
                $errors[] = sprintf('Hotel balance %.2f does not equal guest + AR + deposit ledger balances (%.2f).', $hotel, $main);
            }
        }

        return $errors;
    }

    /**
     * Every numerator / denominator combination with a positive denominator.
     *
     * @param  list<int|float|null>  $numerators
     * @param  list<int|float|null>  $denominators
     * @return list<float>
     */
    private function ratios(array $numerators, array $denominators, int $multiplier = 1): array
    {
        $out = [];
        foreach ($numerators as $n) {
            foreach ($denominators as $d) {
                if ($n !== null && $d !== null) {
                    // A zero denominator prints as 0.00 on Fandaqah and Opera reports.
                    $out[] = $d > 0 ? $n / $d * $multiplier : 0.0;
                }
            }
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    /** @param list<float> $candidates */
    private function matchesAny(float $value, array $candidates, float $tolerance, float $relative = 0.0): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->isClose($value, $candidate, max($tolerance, abs($candidate) * $relative))) {
                return true;
            }
        }

        return false;
    }

    private function isClose(float $a, float $b, float $tolerance): bool
    {
        return abs($a - $b) <= $tolerance + 1e-9;
    }

    /** @param list<float> $values */
    private function fmt(array $values): string
    {
        return implode(' or ', array_map(fn (float $v) => number_format($v, 2, '.', ''), $values)) ?: 'n/a';
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }
}
