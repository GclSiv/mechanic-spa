<?php

namespace Tests\Unit;

use App\Support\TaxCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function test_general_rate_16_applies_to_parts_and_labor(): void
    {
        $r = TaxCalculator::compute(1000, 500, 16);

        $this->assertSame(1500.0, $r['subtotal']);
        $this->assertSame(240.0, $r['tax']);
        $this->assertSame(1740.0, $r['total']);
    }

    public function test_frontier_rate_8_applies_to_parts_and_labor(): void
    {
        $r = TaxCalculator::compute(1000, 500, 8);

        $this->assertSame(1500.0, $r['subtotal']);
        $this->assertSame(120.0, $r['tax']);
        $this->assertSame(1620.0, $r['total']);
    }

    public function test_rounds_to_cents_at_the_end(): void
    {
        // 33.33 * 16% = 5.3328 -> 5.33
        $r = TaxCalculator::compute(33.33, 0, 16);

        $this->assertSame(5.33, $r['tax']);
        $this->assertSame(38.66, $r['total']);
    }

    public function test_empty_order_is_zero(): void
    {
        $r = TaxCalculator::compute(0, 0, 16);

        $this->assertSame(0.0, $r['total']);
    }

    public function test_rejects_unsupported_rates_such_as_us_8_75(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TaxCalculator::compute(100, 0, 8.75);
    }
}
