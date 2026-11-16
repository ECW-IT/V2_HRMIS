<?php

namespace Modules\Payroll\Tests\Unit;

use Modules\Payroll\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_to_cents_and_back(): void
    {
        $this->assertSame(6000000, Money::toCents('60000.00'));
        $this->assertSame(11560, Money::toCents('115.6'));
        $this->assertSame(-1234, Money::toCents('-12.34'));
        $this->assertSame('115.63', Money::fromCents(11563));
        $this->assertSame('-0.05', Money::fromCents(-5));
        $this->assertSame('0.00', Money::fromCents(0));
        $this->assertSame(2450, Money::toHundredths('24.5'));
    }

    public function test_ratio_rounds_half_up(): void
    {
        $this->assertSame(11563, Money::ratio(4500000, 37, 14400));
        $this->assertSame(4067308, Money::ratio(4500000, 2350, 2600));
        $this->assertSame(1, Money::ratio(1, 1, 2));
        $this->assertSame(-1, Money::ratio(-1, 1, 2));
        $this->assertSame(0, Money::ratio(1, 1, 3));
    }

    public function test_bad_input_is_refused(): void
    {
        foreach (['12.345', 'abc', '', '1,000.00'] as $bad) {
            try {
                Money::toCents($bad);
                $this->fail("Expected InvalidArgumentException for {$bad}");
            } catch (\InvalidArgumentException) {
                // expected
            }
        }
    }
}
