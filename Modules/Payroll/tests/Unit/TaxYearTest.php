<?php

namespace Modules\Payroll\Tests\Unit;

use Modules\Payroll\Support\TaxYear;
use PHPUnit\Framework\TestCase;

class TaxYearTest extends TestCase
{
    public function test_start_end_and_label(): void
    {
        $this->assertSame('2026-04-01', TaxYear::startFor('2026-12-15'));
        $this->assertSame('2026-04-01', TaxYear::startFor('2027-03-31'));
        $this->assertSame('2027-04-01', TaxYear::startFor('2027-04-01'));
        $this->assertSame('2027-03-31', TaxYear::endFor('2026-04-01'));
        $this->assertSame('2026/2027', TaxYear::label('2026-04-01'));
    }
}
