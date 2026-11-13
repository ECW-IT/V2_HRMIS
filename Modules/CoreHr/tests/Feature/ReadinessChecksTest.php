<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonContact;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\DocumentService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ReadinessChecksTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private static int $deviceUserId = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(DocumentTypeSeeder::class);
    }

    private function ready(Company $c, string $hire = '2026-01-01', array $emp = []): Employment
    {
        $e = $this->hired($hire, $c, $emp);

        app(BankAccountService::class)->set($e, $this->account(['effective_from' => $hire]));
        app(EnrolmentService::class)->enrol($e, 'NIT', self::$deviceUserId++, $hire);

        PersonContact::query()->create([
            'person_id' => $e->person_id,
            'type' => ContactType::MOBILE,
            'value' => '0771234567',
            'is_primary' => true,
        ]);

        $mandatory = DocumentType::query()->where('is_mandatory', true)->where('is_active', true)->get();
        foreach ($mandatory as $type) {
            $this->addDocument($e->person, $type->code);
        }

        return $e->fresh();
    }

    private function addDocument(Person $person, string $typeCode, ?string $expiresOn = null): void
    {
        $type = DocumentType::query()->where('code', $typeCode)->first()
            ?? DocumentType::factory()->create(['code' => $typeCode, 'requires_expiry' => $expiresOn !== null]);

        $meta = ['document_type_id' => $type->id];
        if ($expiresOn !== null) {
            $meta['expires_on'] = $expiresOn;
            $meta['issued_on'] = '2026-01-01';
        }

        app(DocumentService::class)->upload(
            $person,
            UploadedFile::fake()->create(strtolower($typeCode).'.pdf', 10, 'application/pdf'),
            $meta,
            null,
        );
    }

    /** @return list<string> */
    private function codes(Employment $e, string $asOf = '2026-11-01'): array
    {
        return app(ReadinessService::class)
            ->forEmployment($e, $asOf)
            ->pluck('code')
            ->sort()
            ->values()
            ->all();
    }

    public function test_fully_set_up_employee_has_no_issues(): void
    {
        $this->assertSame([], $this->codes($this->ready($this->company())));
    }

    public function test_hr001_fails_when_there_is_no_assignment_on_the_date(): void
    {
        $e = $this->ready($this->company(), '2026-06-01');
        Assignment::where('employment_id', $e->id)->update(['effective_from' => '2026-12-01']);
        $this->assertContains('HR001', $this->codes($e));
    }

    public function test_hr002_fails_when_the_grade_was_deactivated(): void
    {
        $e = $this->ready($this->company());
        Grade::whereKey($e->currentAssignment()->grade_id)->update(['is_active' => false]);
        $this->assertSame(['HR002'], $this->codes($e));
    }

    public function test_hr003_fails_without_epf_number_but_ignores_interns(): void
    {
        $c = $this->company();
        $liable = $this->ready($c);
        DB::table('employments')->where('id', $liable->id)->update(['epf_no' => '']);
        $intern = $this->ready($c, emp: ['type' => 'INTERN']);

        $this->assertSame(['HR003'], $this->codes($liable));
        $this->assertSame([], $this->codes($intern));
    }

    public function test_hr004_fails_without_bank_account_and_passes_for_cash(): void
    {
        $c = $this->company();
        $none = $this->ready($c);
        EmployeeBankAccount::where('employment_id', $none->id)->delete();
        $cash = $this->ready($c);
        EmployeeBankAccount::where('employment_id', $cash->id)->delete();
        app(BankAccountService::class)->set($cash, ['effective_from' => '2026-01-01', 'is_cash' => true]);

        $this->assertSame(['HR004'], $this->codes($none));
        $this->assertSame([], $this->codes($cash));
    }

    public function test_hr004_looks_at_the_date_asked_for(): void
    {
        $e = $this->ready($this->company(), '2026-01-01');
        EmployeeBankAccount::where('employment_id', $e->id)->update(['effective_from' => '2026-06-01']);
        $this->assertContains('HR004', $this->codes($e, '2026-05-31'));
        $this->assertNotContains('HR004', $this->codes($e, '2026-06-01'));
    }

    public function test_hr005_fails_when_the_enrolment_ended_before_the_date(): void
    {
        $e = $this->ready($this->company());
        BiometricEnrolment::where('employment_id', $e->id)->update(['valid_to' => '2026-10-31']);
        $this->assertSame(['HR005'], $this->codes($e, '2026-11-01'));
        $this->assertSame([], $this->codes($e, '2026-10-31'));
    }

    public function test_hr006_fails_when_the_manager_has_exited(): void
    {
        $c = $this->company();
        $boss = $this->ready($c);
        $e = $this->ready($c);
        app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
        app(EmploymentService::class)->exit($boss, '2026-09-30', ExitReason::RESIGNATION);
        $this->assertSame(['HR006'], $this->codes($e));
    }

    public function test_hr007_reports_the_import_mismatch_flag(): void
    {
        $e = $this->ready($this->company());
        $e->person->update(['nic_mismatch' => true]);
        $this->assertSame(['HR007'], $this->codes($e));
    }

    public function test_hr008_fails_only_after_the_due_date(): void
    {
        $e = $this->ready($this->company(), '2026-03-01', ['type' => 'PROBATION']);
        $this->assertNotContains('HR008', $this->codes($e, '2026-09-01'));
        $this->assertContains('HR008', $this->codes($e, '2026-09-02'));
    }

    public function test_hr009_fails_when_a_mandatory_document_is_missing_or_archived(): void
    {
        $e = $this->ready($this->company());
        $doc = $e->person->documents()->first();
        app(DocumentService::class)->archive($doc);
        $this->assertSame(['HR009'], $this->codes($e));
    }

    public function test_hr010_fails_inside_thirty_days_of_expiry(): void
    {
        $e = $this->ready($this->company());
        $this->addDocument($e->person, 'DRIVING_LICENCE', expiresOn: '2026-12-01');
        $this->assertNotContains('HR010', $this->codes($e, '2026-10-31'));
        $this->assertContains('HR010', $this->codes($e, '2026-11-01'));
        $this->assertContains('HR010', $this->codes($e, '2026-12-15'));
    }

    public function test_hr011_fails_without_a_mobile_contact(): void
    {
        $e = $this->ready($this->company());
        $e->person->contacts()->delete();
        $this->assertSame(['HR011'], $this->codes($e));
    }

    public function test_exited_employees_are_never_reported(): void
    {
        $c = $this->company();
        $e = $this->ready($c);
        EmployeeBankAccount::where('employment_id', $e->id)->delete();
        app(EmploymentService::class)->exit($e, '2026-10-31', ExitReason::RESIGNATION);
        $this->assertCount(0, app(ReadinessService::class)->run($c, '2026-11-01'));
    }

    public function test_employee_not_yet_hired_on_the_date_is_not_reported(): void
    {
        $c = $this->company();
        $this->hired('2026-12-01', $c);
        $this->assertCount(0, app(ReadinessService::class)->run($c, '2026-11-01'));
    }

    public function test_run_is_limited_to_the_company(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->hired('2026-01-01', $a);
        $this->hired('2026-01-01', $b);
        $ids = app(ReadinessService::class)->run($a, '2026-11-01')->pluck('employmentId')->unique();
        $this->assertCount(1, $ids);
    }

    public function test_summary_lists_every_check_including_zero_counts(): void
    {
        $c = $this->company();
        $this->ready($c);
        $summary = app(ReadinessService::class)->summary($c, '2026-11-01');
        $this->assertSame(['HR001', 'HR002', 'HR003', 'HR004', 'HR005', 'HR006', 'HR007', 'HR008', 'HR009', 'HR010', 'HR011'], array_keys($summary));
        $this->assertSame(0, array_sum(array_column($summary, 'count')));
    }

    public function test_checks_do_not_query_per_employee(): void
    {
        $c = $this->company();
        foreach (range(1, 20) as $i) {
            $this->ready($c);
        }
        DB::enableQueryLog();
        app(ReadinessService::class)->run($c, '2026-11-01');
        $this->assertLessThanOrEqual(25, count(DB::getQueryLog()));
    }
}
