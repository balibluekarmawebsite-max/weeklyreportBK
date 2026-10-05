<?php

namespace Tests\Unit;

use App\Support\ReportCalculator;
use PHPUnit\Framework\TestCase;

class ReportCalculatorTest extends TestCase
{
    public function test_rate_divides_revenue_by_room_nights(): void
    {
        $this->assertSame(2_000_000.0, ReportCalculator::rate(100_000_000, 50));
    }

    public function test_rate_is_null_when_room_nights_zero_or_null(): void
    {
        $this->assertNull(ReportCalculator::rate(100, 0));
        $this->assertNull(ReportCalculator::rate(100, null));
    }

    public function test_share_percent(): void
    {
        $this->assertSame(25.0, ReportCalculator::sharePercent(25, 100));
        $this->assertNull(ReportCalculator::sharePercent(25, 0));
    }

    public function test_production_totals_sums_and_blends_arr(): void
    {
        $rows = [
            (object) ['rn_sold' => 50, 'gross_revenue' => 150_000_000],
            (object) ['rn_sold' => 50, 'gross_revenue' => 50_000_000],
            (object) ['rn_sold' => null, 'gross_revenue' => null], // no production this week
        ];

        $t = ReportCalculator::productionTotals($rows);

        $this->assertSame(100, $t['rn']);
        $this->assertSame(200_000_000.0, $t['revenue']);
        $this->assertSame(2_000_000.0, $t['arr']);
    }

    public function test_monthly_totals(): void
    {
        $rows = [
            (object) ['rn_sold' => 400, 'rev_actual' => 800_000_000, 'rev_budget' => 900_000_000, 'rev_ly' => 700_000_000],
            (object) ['rn_sold' => 100, 'rev_actual' => 200_000_000, 'rev_budget' => 100_000_000, 'rev_ly' => 300_000_000],
        ];

        $t = ReportCalculator::monthlyTotals($rows);

        $this->assertSame(500, $t['rn_sold']);
        $this->assertSame(1_000_000_000.0, $t['rev_actual']);
        $this->assertSame(2_000_000.0, $t['arr_actual']); // 1,000,000,000 / 500
    }

    public function test_variance_percent(): void
    {
        $this->assertEqualsWithDelta(-7.42, ReportCalculator::variancePercent(0.853, 0.9214), 0.01);
        $this->assertNull(ReportCalculator::variancePercent(100, 0));
    }
}
