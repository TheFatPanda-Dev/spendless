<?php

namespace Tests\Unit\Assets;

use App\Services\Assets\FixedTermSavings;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FixedTermSavingsTest extends TestCase
{
    public function test_profit_is_amount_times_rate_times_years_paid_at_the_end(): void
    {
        $isa = new FixedTermSavings(20000, 4.25, CarbonImmutable::parse('2026-01-01'), 2);

        $this->assertSame('2028-01-01', $isa->maturityDate()->toDateString());
        $this->assertSame(1700.0, $isa->totalProfit());
        $this->assertSame(21700.0, $isa->valueAtMaturity());

        $this->assertSame(20000.0, $isa->valueOn(CarbonImmutable::parse('2026-01-01')));
        $this->assertEqualsWithDelta(850.0, $isa->profitEarned(CarbonImmutable::parse('2027-01-01')), 1.5);
        $this->assertFalse($isa->hasMatured(CarbonImmutable::parse('2027-12-31')));
        $this->assertTrue($isa->hasMatured(CarbonImmutable::parse('2028-01-01')));
        $this->assertSame(21700.0, $isa->valueOn(CarbonImmutable::parse('2030-06-01')), 'Capped at maturity');
    }

    public function test_part_years_and_missing_terms(): void
    {
        $isa = new FixedTermSavings(10000, 5, CarbonImmutable::parse('2026-03-15'), 1.5);

        $this->assertSame('2027-09-15', $isa->maturityDate()->toDateString());
        $this->assertSame(750.0, $isa->totalProfit());
        $this->assertNull(FixedTermSavings::fromTerms(['amount' => 1]));
    }
}
