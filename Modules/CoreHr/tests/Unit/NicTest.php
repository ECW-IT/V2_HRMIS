<?php

namespace Modules\CoreHr\Tests\Unit;

use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Support\Nic;
use PHPUnit\Framework\TestCase;

class NicTest extends TestCase
{
    public function test_twelve_digit_nic_is_unchanged(): void
    {
        $this->assertSame('198534000937', Nic::normalise('198534000937'));
    }

    public function test_old_format_converts_to_twelve_digits(): void
    {
        $this->assertSame('198534000937', Nic::normalise('853400937V'));
        $this->assertSame('198534000937', Nic::normalise(' 853400937v '));
        $this->assertSame('199062300123', Nic::normalise('906230123X'));
    }

    public function test_garbage_is_refused(): void
    {
        foreach (['', '12345', '85340093V', '8534009371', 'ABCDEFGHIJKL', '853400937VV', '1985340009371', '853400937A'] as $bad) {
            try {
                Nic::normalise($bad);
                $this->fail("accepted [$bad]");
            } catch (InvalidNic) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_impossible_day_number_is_refused(): void
    {
        foreach (['850000937V', '853670937V', '859000937V'] as $bad) {      // day 000, 367, 900
            try {
                Nic::normalise($bad);
                $this->fail("accepted [$bad]");
            } catch (InvalidNic) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_gender(): void
    {
        $this->assertSame(Gender::MALE, Nic::gender('198534000937'));
        $this->assertSame(Gender::FEMALE, Nic::gender('199062300123'));
    }

    public function test_date_of_birth_before_march(): void
    {
        $this->assertSame('1985-01-01', Nic::dateOfBirth('198500100937')->toDateString());
        $this->assertSame('1985-02-28', Nic::dateOfBirth('198505900937')->toDateString());
    }

    public function test_date_of_birth_uses_the_366_day_calendar(): void
    {
        $this->assertSame('1985-03-01', Nic::dateOfBirth('198506100937')->toDateString());   // day 61
        $this->assertSame('1990-05-02', Nic::dateOfBirth('199012300123')->toDateString());   // day 123
        $this->assertSame('1985-12-05', Nic::dateOfBirth('198534000937')->toDateString());   // day 340
        $this->assertSame('1985-12-31', Nic::dateOfBirth('198536600937')->toDateString());   // day 366
    }

    public function test_date_of_birth_for_female_subtracts_500(): void
    {
        $this->assertSame('1990-05-02', Nic::dateOfBirth('199062300123')->toDateString());   // 623 -> 123
    }

    public function test_leap_day(): void
    {
        $this->assertSame('1988-02-29', Nic::dateOfBirth('198806000937')->toDateString());   // 1988 is a leap year
        $this->expectException(InvalidNic::class);
        Nic::dateOfBirth('198506000937');                                                    // 1985 is not
    }
}
