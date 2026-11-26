<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Models\DeviceGroup;
use Modules\CoreHr\Exports\FingerprintEnrolmentTemplateExport;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class FingerprintExcelEnrolTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-06-15');
    }

    public function test_download_fingerprint_template_includes_enrolment_headings(): void
    {
        $company = $this->company();
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Night', 'is_active' => true]);
        DeviceGroup::factory()->create(['code' => 'OLD', 'name' => 'Retired', 'is_active' => false]);

        Excel::fake();

        Livewire::test(EmployeesIndex::class)
            ->call('downloadFingerprintTemplate');

        Excel::assertDownloaded('fingerprint_enrolment_template.xlsx', function (FingerprintEnrolmentTemplateExport $export) {
            $sheets = $export->sheets();
            $headings = $sheets[0]->headings();
            $codes = array_column($sheets[1]->array(), 0);

            return count($sheets) === 2
                && $sheets[0]->title() === 'enrolments'
                && $sheets[1]->title() === 'device_groups'
                && $headings === ['employee_no', 'device_group', 'fingerprint_id', 'valid_from']
                && in_array('NIT', $codes, true)
                && ! in_array('OLD', $codes, true)
                && ($sheets[0]->array()[0][0] ?? null) === FingerprintEnrolmentTemplateExport::EXAMPLE_EMPLOYEE_NO;
        });
    }

    public function test_upload_enrols_fingerprint_and_turns_attendance_on(): void
    {
        $company = $this->company();
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $group = DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Night', 'is_active' => true]);
        $current = $this->hired('2026-01-01', $company, ['attendance_active' => false]);
        $future = $this->hired('2026-06-20', $company, ['attendance_active' => false]);

        $upload = $this->makeUpload([
            [$current->employee_no, $group->code, '42', ''],
            [$future->employee_no, $group->code, '43', ''],
        ]);

        Livewire::test(EmployeesIndex::class)
            ->call('openFingerprintUpload')
            ->set('fingerprintExcelFile', $upload)
            ->call('uploadFingerprints')
            ->assertHasNoErrors()
            ->assertSet('fingerprintImportErrors', [])
            ->assertSee('Success: 2');

        $currentEnrolment = BiometricEnrolment::query()->where('employment_id', $current->id)->first();
        $this->assertNotNull($currentEnrolment);
        $this->assertSame('NIT', $currentEnrolment->device_group);
        $this->assertSame(42, $currentEnrolment->device_user_id);
        $this->assertSame('2026-06-15', $currentEnrolment->valid_from->toDateString());
        $this->assertNull($currentEnrolment->valid_to);
        $this->assertTrue($current->fresh()->attendance_active);

        $futureEnrolment = BiometricEnrolment::query()->where('employment_id', $future->id)->first();
        $this->assertNotNull($futureEnrolment);
        $this->assertSame('2026-06-20', $futureEnrolment->valid_from->toDateString());
        $this->assertTrue($future->fresh()->attendance_active);
    }

    public function test_upload_reports_a_fingerprint_already_held_in_the_group(): void
    {
        $company = $this->company();
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $group = DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Night', 'is_active' => true]);
        $holder = $this->hired('2026-01-01', $company);
        $other = $this->hired('2026-01-01', $company);

        $upload = $this->makeUpload([
            [$holder->employee_no, $group->code, '42', '2026-06-15'],
            [$other->employee_no, $group->code, '42', '2026-06-15'],
        ]);

        $component = Livewire::test(EmployeesIndex::class)
            ->call('openFingerprintUpload')
            ->set('fingerprintExcelFile', $upload)
            ->call('uploadFingerprints')
            ->assertHasNoErrors()
            ->assertSee('Success: 1')
            ->assertSee('Errors: 1');

        $errors = $component->get('fingerprintImportErrors');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('already held by '.$holder->employee_no, $errors[0]);

        $this->assertSame(1, BiometricEnrolment::query()->where('device_group', 'NIT')->where('device_user_id', 42)->count());
        $this->assertSame(0, BiometricEnrolment::query()->where('employment_id', $other->id)->count());
    }

    public function test_upload_skips_the_sample_row(): void
    {
        $company = $this->company();
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $group = DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Night', 'is_active' => true]);
        $employment = $this->hired('2026-01-01', $company);

        $upload = $this->makeUpload([
            [FingerprintEnrolmentTemplateExport::EXAMPLE_EMPLOYEE_NO, $group->code, '1', ''],
            [$employment->employee_no, $group->code, '42', '2026-06-01'],
        ]);

        Livewire::test(EmployeesIndex::class)
            ->call('openFingerprintUpload')
            ->set('fingerprintExcelFile', $upload)
            ->call('uploadFingerprints')
            ->assertHasNoErrors()
            ->assertSet('fingerprintImportErrors', [])
            ->assertSee('Success: 1');

        $this->assertSame(1, BiometricEnrolment::query()->count());
        $this->assertSame(
            42,
            BiometricEnrolment::query()->where('employment_id', $employment->id)->value('device_user_id')
        );
    }

    public function test_second_upload_of_the_same_id_does_not_open_another_row(): void
    {
        $company = $this->company();
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $group = DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Night', 'is_active' => true]);
        $employment = $this->hired('2026-01-01', $company);

        $upload = $this->makeUpload([
            [$employment->employee_no, $group->code, '42', '2026-06-01'],
        ]);

        $component = Livewire::test(EmployeesIndex::class)
            ->call('openFingerprintUpload')
            ->set('fingerprintExcelFile', $upload)
            ->call('uploadFingerprints')
            ->assertSet('fingerprintImportErrors', [])
            ->assertSee('Success: 1');

        $component
            ->set('fingerprintExcelFile', $upload)
            ->call('uploadFingerprints')
            ->assertSet('fingerprintImportErrors', [])
            ->assertSee('Success: 1');

        $this->assertSame(
            1,
            BiometricEnrolment::query()
                ->where('employment_id', $employment->id)
                ->whereNull('valid_to')
                ->count()
        );
        $this->assertSame(1, BiometricEnrolment::query()->where('employment_id', $employment->id)->count());
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeUpload(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray, WithHeadings
        {
            /** @param  list<list<mixed>>  $rows */
            public function __construct(private array $rows) {}

            public function headings(): array
            {
                return FingerprintEnrolmentTemplateExport::headings();
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $relative = 'testing/fingerprints-'.uniqid('', true).'.xlsx';
        Excel::store($export, $relative, 'local');
        $path = Storage::disk('local')->path($relative);

        return UploadedFile::fake()->createWithContent(
            'fingerprints.xlsx',
            (string) file_get_contents($path)
        );
    }
}
