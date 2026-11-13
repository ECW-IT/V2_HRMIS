<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\CoreHr\Livewire\BankBranchesIndex;
use Modules\CoreHr\Livewire\BanksIndex;
use Modules\CoreHr\Livewire\Concerns\LoadsBankOptions;
use Modules\CoreHr\Livewire\DocumentTypesIndex;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\DocumentType;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class SettingsMasterDataTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_settings_view_is_required(): void
    {
        $c = $this->company();
        $this->loginAs('hr-officer', $c);

        $this->get(route('settings.banks.index'))->assertForbidden();
        $this->get(route('settings.bank-branches.index'))->assertForbidden();
        $this->get(route('settings.document-types.index'))->assertForbidden();
    }

    public function test_settings_manage_is_required_for_create_and_toggle(): void
    {
        $c = $this->company();

        $this->loginAs('payroll-officer', $c);
        $this->get(route('settings.banks.index'))->assertForbidden();

        $this->loginAs('hr-manager', $c);
        $bank = Bank::factory()->create(['is_active' => true]);

        Livewire::test(BanksIndex::class)
            ->call('openCreate')
            ->assertSuccessful();

        Livewire::test(BanksIndex::class)
            ->call('toggleActive', $bank->id)
            ->assertSuccessful();

        $this->assertFalse($bank->fresh()->is_active);
    }

    public function test_can_create_bank_branch_and_document_type(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(BanksIndex::class)
            ->call('openCreate')
            ->set('code', '7010')
            ->set('name', 'Bank of Ceylon')
            ->call('save')
            ->assertHasNoErrors();

        $bank = Bank::query()->where('code', '7010')->sole();
        $this->assertSame('Bank of Ceylon', $bank->name);
        $this->assertTrue($bank->is_active);

        Livewire::test(BankBranchesIndex::class)
            ->call('openCreate')
            ->set('bank_id', $bank->id)
            ->set('code', '001')
            ->set('name', 'Colombo Branch')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            BankBranch::query()->where('bank_id', $bank->id)->where('code', '001')->exists()
        );

        Livewire::test(DocumentTypesIndex::class)
            ->call('openCreate')
            ->set('code', 'PASSPORT')
            ->set('name', 'Passport copy')
            ->set('requires_expiry', true)
            ->set('is_mandatory', false)
            ->call('save')
            ->assertHasNoErrors();

        $type = DocumentType::query()->where('code', 'PASSPORT')->sole();
        $this->assertTrue($type->requires_expiry);
        $this->assertTrue($type->is_active);
    }

    public function test_deactivated_bank_is_hidden_from_bank_options(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $active = Bank::factory()->create(['is_active' => true, 'name' => 'Active Bank']);
        $inactive = Bank::factory()->create(['is_active' => false, 'name' => 'Inactive Bank']);
        BankBranch::factory()->create(['bank_id' => $active->id, 'is_active' => true]);
        BankBranch::factory()->create(['bank_id' => $active->id, 'is_active' => false]);

        $probe = new class
        {
            use LoadsBankOptions;

            public $bankId = null;

            public $bankBranchId = null;

            public function options(): array
            {
                return $this->bankOptions();
            }
        };

        $options = $probe->options();
        $this->assertTrue($options['banks']->contains('id', $active->id));
        $this->assertFalse($options['banks']->contains('id', $inactive->id));

        $probe->bankId = $active->id;
        $branchOptions = $probe->options()['branches'];
        $this->assertSame(1, $branchOptions->count());
    }

    public function test_deactivated_document_type_is_not_active(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $type = DocumentType::factory()->create(['is_active' => true]);

        Livewire::test(DocumentTypesIndex::class)
            ->call('toggleActive', $type->id)
            ->assertSuccessful();

        $this->assertFalse($type->fresh()->is_active);
        $this->assertSame(
            0,
            DocumentType::query()->where('is_active', true)->whereKey($type->id)->count()
        );
    }

    public function test_branch_code_unique_per_bank(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $bank = Bank::factory()->create();
        BankBranch::factory()->create(['bank_id' => $bank->id, 'code' => '100']);

        Livewire::test(BankBranchesIndex::class)
            ->call('openCreate')
            ->set('bank_id', $bank->id)
            ->set('code', '100')
            ->set('name', 'Duplicate')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_bank_filter_on_branches_index(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $a = Bank::factory()->create();
        $b = Bank::factory()->create();
        BankBranch::factory()->create(['bank_id' => $a->id, 'name' => 'Only A']);
        BankBranch::factory()->create(['bank_id' => $b->id, 'name' => 'Only B']);

        Livewire::test(BankBranchesIndex::class, ['bankFilter' => $a->id])
            ->assertSee('Only A')
            ->assertDontSee('Only B');
    }
}
