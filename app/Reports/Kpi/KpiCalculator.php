<?php

namespace App\Reports\Kpi;

/**
 * The only place FGRE computes hotel KPIs. USALI 11th edition definitions, applied to
 * every PMS so hotels compare like for like:
 *
 *   rooms available = rooms in hotel (out-of-order rooms stay in inventory; only long-term
 *                     removals such as renovation are taken out, by the PMS room count)
 *   rooms sold      = rooms occupied - complimentary - house use (paid rooms)
 *   Occupancy %     = rooms sold / rooms available x 100
 *   ADR             = room revenue / rooms sold
 *   RevPAR          = room revenue / rooms available
 *   TRevPAR         = total revenue / rooms available
 *   Occupancy excl. out of order = rooms sold / (rooms available - out of order) x 100 (secondary)
 *
 * Day use is not a room night. A zero or negative denominator gives null.
 * Over several hotels or days, pass the *summed* parts: ratios are recomputed,
 * never averaged. Money math uses bcmath strings, rounded half away from zero
 * to 2 decimals once at the end.
 */
final class KpiCalculator
{
    private const SCALE = 6;

    /** Parts that are summed across hotels / days. */
    public const PARTS = [
        'total_rooms', 'rooms_occupied', 'ooo_rooms', 'oos_rooms', 'comp_rooms', 'house_use_rooms',
        'day_use_rooms', 'room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue',
        'persons_in_house', 'arrival_rooms', 'departure_rooms', 'no_show_rooms', 'cancellations',
    ];

    /**
     * @param  array<string, int|float|string|null>  $parts
     * @return array{occupancy_pct: ?string, adr: ?string, revpar: ?string, trevpar: ?string, occupancy_excl_ooo_pct: ?string, sellable_rooms: ?int, paid_occupied: ?int}
     */
    public static function compute(array $parts): array
    {
        $total = $parts['total_rooms'] ?? null;
        $occupied = $parts['rooms_occupied'] ?? null;

        // USALI: out-of-order rooms remain available. Comp / house use not printed count as zero.
        $available = $total === null ? null : (int) $total;
        $exclOoo = $total === null ? null : (int) $total - (int) ($parts['ooo_rooms'] ?? 0);
        $sold = $occupied === null ? null
            : (int) $occupied - (int) ($parts['comp_rooms'] ?? 0) - (int) ($parts['house_use_rooms'] ?? 0);

        $roomRevenue = self::str($parts['room_revenue'] ?? null);
        $totalRevenue = self::str($parts['total_revenue'] ?? null);
        $soldStr = $sold === null ? null : (string) $sold;

        return [
            'occupancy_pct' => self::ratio($soldStr, $available, 100),
            'adr' => self::ratio($roomRevenue, $sold),
            'revpar' => self::ratio($roomRevenue, $available),
            'trevpar' => self::ratio($totalRevenue, $available),
            'occupancy_excl_ooo_pct' => self::ratio($soldStr, $exclOoo, 100),
            'sellable_rooms' => $available,
            'paid_occupied' => $sold,
        ];
    }

    /**
     * Sum parts of several rows (hotels or days). A part is null only when no row has it.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array<string, ?string>
     */
    public static function sumParts(iterable $rows): array
    {
        $sums = array_fill_keys(self::PARTS, null);

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row);
            foreach (self::PARTS as $part) {
                if (isset($row[$part])) {
                    // Money keeps 2 decimals; room and guest counts stay whole numbers.
                    $scale = str_ends_with($part, '_revenue') ? 2 : 0;
                    $sums[$part] = bcadd($sums[$part] ?? '0', self::str($row[$part]), $scale);
                }
            }
        }

        return $sums;
    }

    /** Round half away from zero to 2 decimals. */
    public static function round(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $offset, self::SCALE), '0', 2);
    }

    private static function ratio(?string $numerator, int|string|null $denominator, int $multiplier = 1): ?string
    {
        if ($numerator === null || $denominator === null || bccomp((string) $denominator, '0', self::SCALE) <= 0) {
            return null;
        }

        $value = bcdiv(bcmul($numerator, (string) $multiplier, self::SCALE), (string) $denominator, self::SCALE);

        return self::round($value);
    }

    private static function str(int|float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_float($value) ? number_format($value, 6, '.', '') : (string) $value;
    }
}
