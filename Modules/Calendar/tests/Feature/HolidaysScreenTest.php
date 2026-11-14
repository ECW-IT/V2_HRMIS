<?php

namespace Modules\Calendar\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Livewire\HolidaysIndex;
use Modules\Calendar\Models\Holiday;
use Modules\Calendar\Services\HolidayService;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HolidaysScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('calendar.manage');
    }

    public function test_screen_needs_org_view_and_saving_needs_calendar_manage(): void
    {
        $without = User::factory()->create(['must_change_password' => false]);
        $this->actingAs($without)->get(route('calendar.holidays.index'))->assertForbidden();

        $viewer = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($viewer)->get(route('calendar.holidays.index'))->assertOk();

        $company = Company::factory()->create();
        $this->actingAs($viewer);
        CompanyContext::set($company->id);

        Livewire::actingAs($viewer)
            ->test(HolidaysIndex::class)
            ->call('openCreate')
            ->assertForbidden();

        $manager = User::factory()->allCompanies()->withPermissions('org.view', 'calendar.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($manager);
        CompanyContext::set($company->id);

        Livewire::actingAs($manager)
            ->test(HolidaysIndex::class)
            ->call('openCreate')
            ->set('date', '2026-12-25')
            ->set('name', 'Christmas Day')
            ->set('kind', HolidayKind::MERCANTILE->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Holiday::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_list_shows_only_the_selected_companys_holidays_for_the_year(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $svc = app(HolidayService::class);
        $svc->add($a, '2026-01-14', 'Thai Pongal A', HolidayKind::PUBLIC);
        $svc->add($a, '2027-05-01', 'May Day A', HolidayKind::MERCANTILE);
        $svc->add($b, '2026-12-25', 'Christmas B', HolidayKind::MERCANTILE);

        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($a->id);

        Livewire::actingAs($user)
            ->test(HolidaysIndex::class)
            ->set('year', 2026)
            ->assertSee('Thai Pongal A')
            ->assertDontSee('May Day A')
            ->assertDontSee('Christmas B');
    }

    public function test_import_with_a_bad_row_saves_nothing_and_shows_the_row_error(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'calendar.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        $file = $this->makeImportUpload([
            ['2027-01-14', 'Thai Pongal', 'PUBLIC'],
            ['14/01/2027', 'x', 'PUBLIC'],
        ]);

        Livewire::actingAs($user)
            ->test(HolidaysIndex::class)
            ->set('importFile', $file)
            ->call('previewImport')
            ->assertSee('Row 2')
            ->call('commitImport');

        $this->assertSame(0, Holiday::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_import_of_valid_rows_saves_all(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'calendar.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        $file = $this->makeImportUpload([
            ['2027-01-14', 'Thai Pongal', 'PUBLIC'],
            ['2027-05-01', 'May Day', 'MERCANTILE'],
        ]);

        Livewire::actingAs($user)
            ->test(HolidaysIndex::class)
            ->set('importFile', $file)
            ->call('previewImport')
            ->assertDontSee('Row 2: invalid')
            ->call('commitImport');

        $this->assertSame(2, Holiday::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     */
    private function makeImportUpload(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray, WithHeadings
        {
            /** @param list<array{0: string, 1: string, 2: string}> $rows */
            public function __construct(private array $rows) {}

            public function headings(): array
            {
                return ['date', 'name', 'kind'];
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $relative = 'testing/holidays-import-'.uniqid('', true).'.xlsx';
        Excel::store($export, $relative, 'local');
        $path = storage_path('app/private/'.$relative);

        return UploadedFile::fake()->createWithContent('holidays.xlsx', file_get_contents($path));
    }
}
