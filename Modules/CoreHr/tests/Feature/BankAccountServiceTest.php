<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Exceptions\InvalidBankAccount;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Services\BankAccountService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class BankAccountServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private BankAccountService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(BankAccountService::class);
    }

    public function test_non_cash_account_needs_bank_branch_number_and_name(): void
    {
        $e = $this->hired('2026-01-01');
        foreach (['bank_id', 'bank_branch_id', 'account_no', 'account_name'] as $missing) {
            try {
                $this->svc->set($e, array_diff_key($this->account(), [$missing => 1]));
                $this->fail($missing);
            } catch (InvalidBankAccount) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_branch_must_belong_to_the_bank(): void
    {
        $e = $this->hired('2026-01-01');
        $this->expectException(InvalidBankAccount::class);
        $this->svc->set($e, $this->account(['bank_branch_id' => BankBranch::factory()->create()->id]));
    }

    public function test_account_number_must_be_six_to_twenty_digits(): void
    {
        $e = $this->hired('2026-01-01');
        foreach (['12345', '12 34 56A', str_repeat('1', 21)] as $bad) {
            try {
                $this->svc->set($e, $this->account(['account_no' => $bad]));
                $this->fail($bad);
            } catch (InvalidBankAccount) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('0012345678', $this->svc->set($e, $this->account(['account_no' => ' 0012345678 ']))->account_no);
    }

    public function test_cash_payment_stores_no_bank_details(): void
    {
        $acc = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'is_cash' => true, 'account_no' => '0012345678']);
        $this->assertTrue($acc->is_cash);
        $this->assertNull($acc->account_no);
    }

    public function test_new_account_closes_the_previous_one(): void
    {
        $e = $this->hired('2026-01-01');
        $old = $this->svc->set($e, $this->account(['effective_from' => '2026-01-01']));
        $new = $this->svc->set($e, $this->account(['effective_from' => '2026-07-01', 'account_no' => '9998887776']));
        $this->assertSame('2026-06-30', $old->fresh()->effective_to->toDateString());
        $this->assertSame($old->id, $this->svc->on($e, '2026-06-30')->id);
        $this->assertSame($new->id, $this->svc->on($e, '2026-07-01')->id);
    }

    public function test_account_before_hire_date_is_refused(): void
    {
        $this->expectException(InvalidBankAccount::class);
        $this->svc->set($this->hired('2026-06-01'), $this->account(['effective_from' => '2026-05-01']));
    }

    public function test_account_number_is_masked_in_the_audit_log(): void
    {
        $acc = $this->svc->set($this->hired('2026-01-01'), $this->account(['account_no' => '0012345678']));
        $this->assertSame('***5678', $acc->auditLogs()->where('event', 'CREATED')->sole()->new_values['account_no']);
    }
}
