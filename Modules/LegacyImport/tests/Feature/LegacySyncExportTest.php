<?php

namespace Modules\LegacyImport\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\LegacyImport\Livewire\LegacySyncIndex;
use Modules\LegacyImport\Models\LegacySyncRun;
use Modules\LegacyImport\Services\LegacySyncExporter;
use Modules\Organisation\Models\Grade;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LegacySyncExportTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_export_lists_hires_exits_and_changes_in_the_window(): void
    {
        $c = $this->company();
        Carbon::setTestNow('2026-11-30 12:00:00');
        $before = $this->hired('2026-01-01', $c);
        Carbon::setTestNow('2026-12-01 09:00:00');
        $new = $this->hired('2026-12-01', $c);
        $g2 = Grade::factory()->create(['code' => 'G2', 'name' => 'Grade Two']);
        app(EmploymentService::class)->changeAssignment($before, '2026-12-01', ['grade_id' => $g2->id], 'Promotion');
        $leaver = $this->hired('2026-06-01', $c);
        app(EmploymentService::class)->exit($leaver, '2026-12-01', ExitReason::RESIGNATION, 'Moving abroad');

        $rows = app(LegacySyncExporter::class)->collect($c, Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));

        // Collection::pluck uses data_get, which splits on '.' — so 'Employee no.' cannot be plucked.
        $this->assertEqualsCanonicalizing(
            [$new->employee_no, $leaver->employee_no],
            $rows['hires']->map(fn (array $r) => $r['Employee no.'])->all()
        );
        $this->assertSame(
            [$leaver->employee_no],
            $rows['exits']->map(fn (array $r) => $r['Employee no.'])->all()
        );
        $this->assertSame('Moving abroad', $rows['exits'][0]['Remarks']);
        $change = $rows['changes']->sole();
        $this->assertSame($before->employee_no, $change['Employee no.']);
        $this->assertSame('2026-12-01', $change['Effective date']);
        $this->assertStringContainsString('Grade Two', $change['To']);
    }

    public function test_back_dated_change_is_caught_by_when_it_was_recorded(): void
    {
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        Carbon::setTestNow('2026-12-01 09:00:00');
        app(EmploymentService::class)->changeAssignment($e, '2026-03-01', ['grade_id' => Grade::factory()->create()->id], 'Late paperwork');

        $rows = app(LegacySyncExporter::class)->collect($c, Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));
        $this->assertSame('2026-03-01', $rows['changes']->sole()['Effective date']);
    }

    public function test_second_run_does_not_repeat_rows(): void
    {
        Storage::fake('private');
        Excel::fake();

        $c = $this->company();
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->hired('2026-12-01', $c);
        Carbon::setTestNow('2026-12-01 18:00:00');
        $first = app(LegacySyncExporter::class)->run($c);
        Carbon::setTestNow('2026-12-02 18:00:00');
        $second = app(LegacySyncExporter::class)->run($c);

        $this->assertSame(1, $first->new_hires);
        $this->assertSame(0, $second->new_hires);
        $this->assertSame($first->to_at->toDateTimeString(), $second->from_at->toDateTimeString());
        $this->assertSame('', $second->file_path);
    }

    public function test_sister_company_transfer_appears_as_an_exit_and_a_hire(): void
    {
        $a = $this->company();
        $b = $this->company();
        $old = $this->hired('2024-03-01', $a, ['epf_no' => '500']);
        Carbon::setTestNow('2026-12-01 09:00:00');
        $new = app(EmploymentService::class)->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);

        $from = Carbon::parse('2026-12-01 00:00:00');
        $to = Carbon::parse('2026-12-01 23:59:59');
        $rowsA = app(LegacySyncExporter::class)->collect($a, $from, $to);
        $rowsB = app(LegacySyncExporter::class)->collect($b, $from, $to);

        $this->assertSame($old->employee_no, $rowsA['exits']->sole()['Employee no.']);
        $hire = $rowsB['hires']->sole();
        $this->assertSame($new->employee_no, $hire['Employee no.']);
        $this->assertSame($old->employee_no, $hire['Old employee no.']);
        $this->assertSame('77', $hire['EPF no.']);
    }

    public function test_bank_account_change_is_listed_but_first_account_is_not(): void
    {
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        $svc = app(BankAccountService::class);
        Carbon::setTestNow('2026-11-30 12:00:00');
        $svc->set($e, $this->account(['effective_from' => '2026-01-01']));
        Carbon::setTestNow('2026-12-01 09:00:00');
        $svc->set($e, $this->account(['effective_from' => '2026-12-01', 'account_no' => '9998887776']));

        $rows = app(LegacySyncExporter::class)->collect($c, Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));
        $change = $rows['changes']->sole();
        $this->assertSame('Bank account', $change['Change']);
        $this->assertStringContainsString('9998887776', $change['To']);
    }

    public function test_screen_and_download_need_bank_view(): void
    {
        $c = $this->company();

        $this->loginAs('hr-officer', $c);
        $this->get(route('legacy-sync.index'))->assertForbidden();

        $this->loginAs('payroll-officer', $c);
        $this->get(route('legacy-sync.index'))->assertOk();
    }

    public function test_mark_as_applied_records_who_and_when(): void
    {
        $c = $this->company();
        Carbon::setTestNow('2026-12-01 18:00:00');
        $run = LegacySyncRun::factory()->create([
            'company_id' => $c->id,
            'from_at' => '2026-12-01 00:00:00',
            'to_at' => '2026-12-01 18:00:00',
            'file_path' => '',
        ]);

        $user = $this->loginAs('payroll-officer', $c);

        Livewire::test(LegacySyncIndex::class)
            ->call('markApplied', $run->id)
            ->assertSuccessful();

        $run->refresh();
        $this->assertSame($user->id, $run->applied_by);
        $this->assertSame('2026-12-01 18:00:00', $run->applied_at->toDateTimeString());
    }

    public function test_run_with_nothing_to_report_writes_no_file(): void
    {
        Storage::fake('private');
        Excel::fake();

        $c = $this->company();
        Carbon::setTestNow('2026-12-01 18:00:00');
        $run = app(LegacySyncExporter::class)->run($c);

        $this->assertSame(0, $run->new_hires);
        $this->assertSame(0, $run->exits);
        $this->assertSame(0, $run->changes);
        $this->assertSame('', $run->file_path);
        $this->assertSame([], Storage::disk('private')->allFiles('legacy-sync'));
    }

    public function test_run_contains_only_its_own_company(): void
    {
        Storage::fake('private');
        Excel::fake();

        $a = $this->company();
        $b = $this->company();
        Carbon::setTestNow('2026-12-01 09:00:00');
        $a1 = $this->hired('2026-12-01', $a);
        $this->hired('2026-12-01', $b);

        $run = app(LegacySyncExporter::class)->run($a);

        $this->assertSame($a->id, $run->company_id);
        $this->assertSame(1, $run->new_hires);
        $rows = app(LegacySyncExporter::class)->collect($a, Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));
        $this->assertSame(
            [$a1->employee_no],
            $rows['hires']->map(fn (array $r) => $r['Employee no.'])->all()
        );
    }

    public function test_user_of_one_company_cannot_see_or_download_another_companys_run(): void
    {
        Storage::fake('private');
        Excel::fake();

        $a = $this->company();
        $b = $this->company();
        Carbon::setTestNow('2026-12-01 18:00:00');
        $runA = app(LegacySyncExporter::class)->run($a);
        $runB = app(LegacySyncExporter::class)->run($b);

        $this->loginAs('payroll-officer', $a);

        Livewire::test(LegacySyncIndex::class)
            ->assertViewHas('runs', fn ($runs) => $runs->pluck('id')->all() === [$runA->id])
            ->call('download', $runB->id)
            ->assertNotFound();
    }

    public function test_each_company_has_its_own_window(): void
    {
        Storage::fake('private');
        Excel::fake();

        $a = $this->company();
        $b = $this->company();

        Carbon::setTestNow('2026-12-01 10:00:00');
        app(LegacySyncExporter::class)->run($a);

        Carbon::setTestNow('2026-12-01 10:05:00');
        $hireB = $this->hired('2026-12-01', $b);
        Carbon::setTestNow('2026-12-01 10:06:00');
        $hireA = $this->hired('2026-12-01', $a);

        Carbon::setTestNow('2026-12-01 10:10:00');
        $runB = app(LegacySyncExporter::class)->run($b);
        $runA = app(LegacySyncExporter::class)->run($a);

        $this->assertSame(1, $runB->new_hires);
        $this->assertSame('2026-12-01 00:00:00', $runB->from_at->toDateTimeString());
        $this->assertSame(
            [$hireB->employee_no],
            app(LegacySyncExporter::class)->collect($b, $runB->from_at, $runB->to_at)['hires']
                ->map(fn (array $r) => $r['Employee no.'])->all()
        );

        $this->assertSame(1, $runA->new_hires);
        $this->assertSame(
            [$hireA->employee_no],
            app(LegacySyncExporter::class)->collect($a, $runA->from_at, $runA->to_at)['hires']
                ->map(fn (array $r) => $r['Employee no.'])->all()
        );
    }

    public function test_command_runs_once_per_active_company(): void
    {
        Storage::fake('private');
        Excel::fake();

        $this->company(['code' => 'AA1', 'is_active' => true]);
        $this->company(['code' => 'BB1', 'is_active' => true]);
        $this->company(['code' => 'XX1', 'is_active' => false]);

        Carbon::setTestNow('2026-12-01 18:00:00');
        Artisan::call('hrmis:export:legacy-sync');

        $this->assertSame(2, LegacySyncRun::query()->count());
        $this->assertEqualsCanonicalizing(
            ['AA1', 'BB1'],
            LegacySyncRun::query()->with('company')->get()->pluck('company.code')->all()
        );
    }
}
