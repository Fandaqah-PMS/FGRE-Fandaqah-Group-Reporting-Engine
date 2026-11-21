<?php

namespace Tests\Unit;

use App\Reports\Kpi\KpiCalculator;
use PHPUnit\Framework\TestCase;

class KpiCalculatorTest extends TestCase
{
    public function test_headline_kpis_exclude_comp_house_and_out_of_order(): void
    {
        $kpi = KpiCalculator::compute([
            'total_rooms' => 100, 'ooo_rooms' => 4, 'rooms_occupied' => 80,
            'comp_rooms' => 3, 'house_use_rooms' => 1, 'room_revenue' => '38000.00', 'total_revenue' => '45000.00',
        ]);

        $this->assertSame(96, $kpi['sellable_rooms']);
        $this->assertSame(76, $kpi['paid_occupied']);
        $this->assertSame('79.17', $kpi['occupancy_pct']);   // 76 / 96 = 79.1666
        $this->assertSame('500.00', $kpi['adr']);            // 38000 / 76
        $this->assertSame('395.83', $kpi['revpar']);         // 38000 / 96 = 395.8333
        $this->assertSame('468.75', $kpi['trevpar']);        // 45000 / 96
    }

    public function test_zero_denominators_give_null(): void
    {
        $kpi = KpiCalculator::compute(['total_rooms' => 0, 'rooms_occupied' => 0, 'room_revenue' => 0]);

        $this->assertNull($kpi['occupancy_pct']);
        $this->assertNull($kpi['adr']);
        $this->assertNull($kpi['revpar']);
    }

    public function test_rounding_is_half_away_from_zero(): void
    {
        $this->assertSame('0.13', KpiCalculator::round('0.125'));
        $this->assertSame('-0.13', KpiCalculator::round('-0.125'));
        $this->assertSame('0.12', KpiCalculator::round('0.124999'));
    }

    public function test_group_ratios_are_recomputed_from_summed_parts_not_averaged(): void
    {
        $small = ['total_rooms' => 10, 'rooms_occupied' => 10, 'room_revenue' => '1000.00'];   // 100 %, ADR 100
        $large = ['total_rooms' => 90, 'rooms_occupied' => 45, 'room_revenue' => '9000.00'];   // 50 %, ADR 200

        $group = KpiCalculator::compute(KpiCalculator::sumParts([$small, $large]));

        // Averaging would give 75 % and ADR 150. The group is 55 rooms of 100, 10000 / 55.
        $this->assertSame('55.00', $group['occupancy_pct']);
        $this->assertSame('181.82', $group['adr']);
    }
}
