<?php

namespace App\Reports\Validation;

use Carbon\CarbonImmutable;

/**
 * Cross-checks AI-extracted report data before it is stored.
 *
 * Any error sends the document to the review queue. Checks compare printed
 * values with values recomputed from other printed values, so a misread
 * digit is caught instead of silently stored.
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
     * @return list<string> human-readable errors; empty when valid
     */
    public function validate(array $output): array
    {
        $errors = [];

        $type = $output['report_type'] ?? 'unknown';
        if ($type === 'unknown') {
            return ['Report type could not be identified.'];
        }

        $errors = [...$errors, ...$this->validateBusinessDate($output['business_date'] ?? null)];

        if (($output['confidence'] ?? 'low') === 'low') {
            $errors[] = 'AI reported low confidence: '.implode('; ', $output['issues'] ?? []);
        }

        return match ($type) {
            'manager_flash' => [...$errors, ...$this->validateFlash($output['manager_flash'] ?? null)],
            'trial_balance' => [...$errors, ...$this->validateTrialBalance($output['trial_balance'] ?? null)],
            default => [...$errors, "Unsupported report type [{$type}]."],
        };
    }

    /** @return list<string> */
    private function validateBusinessDate(?string $date): array
    {
        if ($date === null) {
            return ['Business date is missing.'];
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return ["Business date [{$date}] is not a valid date."];
        }

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return ["Business date [{$date}] is not a valid date."];
        }

        if ($parsed->isAfter(CarbonImmutable::today()->addDay())) {
            return ["Business date [{$date}] is in the future."];
        }

        return [];
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

        foreach (['day', 'mtd', 'ytd'] as $period) {
            $m = [];
            foreach ($flash['metrics'] as $row) {
                $m[$row['metric']] = $row[$period] ?? null;
            }

            $available = $m['rooms_available'] ?? null;
            $sold = $m['rooms_sold'] ?? null;
            $ooo = $m['rooms_out_of_order'] ?? null;
            $roomRevenue = $m['room_revenue'] ?? null;
            $label = strtoupper($period);

            if ($available !== null && $sold !== null && $sold > $available) {
                $errors[] = "{$label}: rooms sold ({$sold}) exceed rooms available ({$available}).";
            }

            // PMSs differ on whether out-of-order rooms are deducted, so accept either definition.
            if ($available && $sold !== null && ($occ = $m['occupancy_pct'] ?? null) !== null) {
                $candidates = [$sold / $available * 100];
                if ($ooo && $available - $ooo > 0) {
                    $candidates[] = $sold / ($available - $ooo) * 100;
                }
                if (! $this->anyClose($occ, $candidates, $this->percentTolerance)) {
                    $errors[] = sprintf('%s: occupancy %.2f%% does not match rooms sold / available (%.2f%%).', $label, $occ, $candidates[0]);
                }
            }

            if ($sold && $roomRevenue !== null && ($adr = $m['adr'] ?? null) !== null) {
                $expected = $roomRevenue / $sold;
                if (! $this->isClose($adr, $expected, max($this->moneyTolerance, abs($expected) * 0.005))) {
                    $errors[] = sprintf('%s: ADR %.2f does not match room revenue / rooms sold (%.2f).', $label, $adr, $expected);
                }
            }

            if ($available && $roomRevenue !== null && ($revpar = $m['revpar'] ?? null) !== null) {
                $candidates = [$roomRevenue / $available];
                if ($ooo && $available - $ooo > 0) {
                    $candidates[] = $roomRevenue / ($available - $ooo);
                }
                $tolerance = max($this->moneyTolerance, abs($candidates[0]) * 0.005);
                if (! $this->anyClose($revpar, $candidates, $tolerance)) {
                    $errors[] = sprintf('%s: RevPAR %.2f does not match room revenue / rooms available (%.2f).', $label, $revpar, $candidates[0]);
                }
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
        $debit = 0.0;
        $credit = 0.0;
        $codes = [];

        foreach ($tb['lines'] as $line) {
            $debit += (float) $line['debit'];
            $credit += (float) $line['credit'];

            $code = $line['account_code'];
            if (isset($codes[$code])) {
                $errors[] = "Account code [{$code}] appears more than once.";
            }
            $codes[$code] = true;

            // Closing balance: debit-normal (o + d - c) or credit-normal printed positive (o - d + c).
            $o = $line['opening_balance'];
            $close = $line['closing_balance'];
            if ($o !== null && $close !== null) {
                $d = (float) $line['debit'];
                $c = (float) $line['credit'];
                if (! $this->anyClose($close, [$o + $d - $c, $o - $d + $c], $this->moneyTolerance)) {
                    $errors[] = sprintf('Account [%s]: opening %.2f + movement does not equal closing %.2f.', $code, $o, $close);
                }
            }
        }

        if (! $this->isClose($debit, $credit, $this->moneyTolerance)) {
            $errors[] = sprintf('Total debit %.2f does not equal total credit %.2f.', $debit, $credit);
        }

        if (($printed = $tb['printed_total_debit'] ?? null) !== null && ! $this->isClose($debit, $printed, $this->moneyTolerance)) {
            $errors[] = sprintf('Sum of debit lines %.2f does not match printed total %.2f.', $debit, $printed);
        }

        if (($printed = $tb['printed_total_credit'] ?? null) !== null && ! $this->isClose($credit, $printed, $this->moneyTolerance)) {
            $errors[] = sprintf('Sum of credit lines %.2f does not match printed total %.2f.', $credit, $printed);
        }

        return $errors;
    }

    private function isClose(float $a, float $b, float $tolerance): bool
    {
        return abs($a - $b) <= $tolerance + 1e-9;
    }

    /** @param list<float> $candidates */
    private function anyClose(float $value, array $candidates, float $tolerance): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->isClose($value, $candidate, $tolerance)) {
                return true;
            }
        }

        return false;
    }
}
