<?php

namespace Modules\Calendar\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Exceptions\InvalidHoliday;
use Modules\Calendar\Imports\HolidaysImport;
use Modules\Calendar\Models\Holiday;
use Modules\Calendar\Services\HolidayService;
use Modules\Calendar\Support\CalendarNav;
use Modules\Organisation\Models\Company;

class HolidaysIndex extends Component
{
    use WithFileUploads;

    public int $year;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $date = '';

    public string $name = '';

    public string $kind = '';

    public string $formError = '';

    public bool $showCopy = false;

    public ?int $copyFromCompanyId = null;

    public int $copyYear;

    public string $copyMessage = '';

    public $importFile = null;

    /** @var list<array{row: int, date: string, name: string, kind: string}> */
    public array $importRows = [];

    /** @var list<string> */
    public array $importErrors = [];

    public string $importMessage = '';

    public function mount(): void
    {
        $this->authorize('org.view');
        $now = Carbon::now(config('app.timezone'));
        $this->year = (int) $now->year;
        $this->copyYear = $this->year;
    }

    public function openCreate(): void
    {
        $this->authorize('calendar.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->formError = '';
        $this->showForm = true;
    }

    public function openEdit(int $holidayId): void
    {
        $this->authorize('calendar.manage');
        $holiday = Holiday::query()->findOrFail($holidayId);
        $this->editingId = $holiday->id;
        $this->date = $holiday->date->format('Y-m-d');
        $this->name = $holiday->name;
        $this->kind = $holiday->kind->value;
        $this->formError = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formError = '';
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->date = '';
        $this->name = '';
        $this->kind = '';
    }

    public function save(HolidayService $service): void
    {
        $this->authorize('calendar.manage');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->formError = 'Select a company first.';

            return;
        }

        $validated = $this->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(HolidayKind::class)],
        ]);

        try {
            if ($this->editingId === null) {
                $company = Company::query()->findOrFail($companyId);
                $service->add(
                    $company,
                    $validated['date'],
                    $validated['name'],
                    HolidayKind::from($validated['kind']),
                );
            } else {
                $holiday = Holiday::query()->findOrFail($this->editingId);
                if ($service->isDateLocked((int) $holiday->company_id, $holiday->date)) {
                    throw new InvalidHoliday('This date is inside a closed pay period and cannot be changed.');
                }
                $holiday->update([
                    'name' => $validated['name'],
                    'kind' => HolidayKind::from($validated['kind']),
                ]);
            }
        } catch (InvalidHoliday $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function remove(int $holidayId, HolidayService $service): void
    {
        $this->authorize('calendar.manage');

        $holiday = Holiday::query()->findOrFail($holidayId);

        try {
            $service->remove($holiday);
        } catch (InvalidHoliday $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function openCopy(): void
    {
        $this->authorize('calendar.manage');
        $this->copyMessage = '';
        $this->showCopy = true;
    }

    public function closeCopy(): void
    {
        $this->showCopy = false;
        $this->copyMessage = '';
    }

    public function copyFromCompany(HolidayService $service): void
    {
        $this->authorize('calendar.manage');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->copyMessage = 'Select a company first.';

            return;
        }

        $validated = $this->validate([
            'copyFromCompanyId' => ['required', 'integer', 'exists:companies,id'],
            'copyYear' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $from = Company::query()->findOrFail($validated['copyFromCompanyId']);
        $to = Company::query()->findOrFail($companyId);

        try {
            $added = $service->copyYear($from, $to, (int) $validated['copyYear']);
            $this->copyMessage = "{$added} holiday(s) copied.";
            $this->showCopy = false;
        } catch (InvalidHoliday $e) {
            $this->copyMessage = $e->getMessage();
        }
    }

    public function previewImport(): void
    {
        $this->authorize('calendar.manage');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $this->importRows = [];
        $this->importErrors = [];
        $this->importMessage = '';

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->importErrors[] = 'Select a company first.';

            return;
        }

        $import = new HolidaysImport;
        Excel::import($import, $this->importFile->getRealPath());

        $this->importRows = $import->rows;

        foreach ($import->errors as $error) {
            $this->importErrors[] = $error['message'];
        }

        $existing = Holiday::query()
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->format('Y-m-d') => true])
            ->all();

        foreach ($this->importRows as $row) {
            if (isset($existing[$row['date']])) {
                $this->importErrors[] = "Row {$row['row']}: a holiday already exists on this date.";
            }
        }
    }

    public function commitImport(HolidayService $service): void
    {
        $this->authorize('calendar.manage');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->importMessage = 'Select a company first.';

            return;
        }

        if ($this->importErrors !== [] || $this->importRows === []) {
            return;
        }

        $company = Company::query()->findOrFail($companyId);

        try {
            foreach ($this->importRows as $row) {
                $service->add(
                    $company,
                    $row['date'],
                    $row['name'],
                    HolidayKind::from($row['kind']),
                );
            }
        } catch (InvalidHoliday $e) {
            $this->importMessage = $e->getMessage();

            return;
        }

        $this->importMessage = count($this->importRows).' holiday(s) imported.';
        $this->importRows = [];
        $this->importErrors = [];
        $this->importFile = null;
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $companyId = CompanyContext::id();
        $holidays = collect();

        if ($companyId !== null) {
            $holidays = Holiday::query()
                ->where('company_id', $companyId)
                ->whereYear('date', $this->year)
                ->orderBy('date')
                ->get();
        }

        $otherCompanies = [];
        if (Auth::check()) {
            $otherCompanies = Company::query()
                ->where('is_active', true)
                ->when($companyId !== null, fn ($q) => $q->where('id', '!=', $companyId))
                ->whereIn('id', CompanyContext::permittedIds())
                ->orderBy('name')
                ->get();
        }

        return view('calendar::livewire.holidays-index', [
            'companyId' => $companyId,
            'holidays' => $holidays,
            'kinds' => HolidayKind::cases(),
            'otherCompanies' => $otherCompanies,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Calendar',
            'title' => 'Holidays',
            'moduleNav' => CalendarNav::items('holidays'),
        ]);
    }
}
