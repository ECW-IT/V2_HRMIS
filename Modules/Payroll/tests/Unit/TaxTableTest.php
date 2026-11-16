<?php

namespace Modules\Payroll\Tests\Unit;

use Modules\Payroll\Calculator\TaxTable;
use Modules\Payroll\Support\Money;
use PHPUnit\Framework\TestCase;

class TaxTableTest extends TestCase
{
    /** @var list<array{width: ?string, percent: string}> */
    private array $slabs = [
        ['width' => '150000.00', 'percent' => '0.00'],
        ['width' => '83333.33', 'percent' => '6.00'],
        ['width' => '41666.67', 'percent' => '18.00'],
        ['width' => '41666.67', 'percent' => '24.00'],
        ['width' => '41666.67', 'percent' => '30.00'],
        ['width' => null, 'percent' => '36.00'],
    ];

    public function test_tax_by_slab(): void
    {
        $cases = [
            '0.00' => '0.00',
            '79825.00' => '0.00',
            '150000.00' => '0.00',
            '200000.00' => '3000.00',
            '300000.00' => '18500.00',
            '330000.00' => '26500.00',
            '500000.00' => '86000.00',
        ];
        foreach ($cases as $taxable => $tax) {
            $this->assertSame($tax, Money::fromCents(TaxTable::taxOn(Money::toCents($taxable), $this->slabs)), $taxable);
        }
    }

    public function test_a_table_with_one_open_slab(): void
    {
        $slabs = [['width' => null, 'percent' => '10.00']];
        $this->assertSame('100.00', Money::fromCents(TaxTable::taxOn(Money::toCents('1000.00'), $slabs)));
    }
}
