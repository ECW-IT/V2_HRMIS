<?php

namespace Modules\Attendance\Tests\Unit;

use Modules\Attendance\Timecards\WorkDate;
use PHPUnit\Framework\TestCase;

class WorkDateTest extends TestCase
{
    public function test_punch_before_five_belongs_to_the_previous_day(): void
    {
        $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-02 00:28:00')));
        $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-02 04:59:59')));
        $this->assertSame('2026-12-02', WorkDate::of(new \DateTimeImmutable('2026-12-02 05:00:00')));
        $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-01 08:00:00')));
    }

    public function test_window(): void
    {
        [$from, $to] = WorkDate::window('2026-12-31');
        $this->assertSame(['2026-12-31 05:00:00', '2027-01-01 05:00:00'], [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
    }
}
