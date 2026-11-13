<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Exceptions\DutyEffectiveFromRequired;
use Modules\Organisation\Models\BreakType;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Services\EmployeeCategoryService;
use Modules\Organisation\Support\OrganisationNav;

class EmployeeCategoriesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $annual_leaves = '';

    public string $casual_leaves = '';

    public string $medical_leaves = '';

    public string $normal_ot_rate = '';

    public string $double_ot_rate = '';

    public string $no_pay_rate_per_hour = '';

    public string $no_pay_rate_per_day = '';

    public string $saturday_rate = '';

    public string $sunday_rate = '';

    public string $payroll_workdays = '0';

    public string $payroll_workhours = '0';

    public string $saturday_ot_mode = '0';

    public string $custom_saturday_ot_type = '';

    public string $sunday_ot_mode = '0';

    public string $custom_sunday_ot_type = '';

    public string $on_duty_time = '';

    public string $off_duty_time = '';

    public string $morning_short_beginning = '';

    public string $morning_short_ending = '';

    public string $evening_short_beginning = '';

    public string $evening_short_ending = '';

    public string $halfday_beginning = '';

    public string $late_on = '';

    public string $duty_effective_from = '';

    /** @var array<int, array{break_type_id: string, start_time: string, end_time: string, duration_minutes: string}> */
    public array $breaks = [];

    /** @var array<string, string> */
    public array $loadedDutyTimes = [];

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function openEdit(int $categoryId): void
    {
        $this->authorize('org.manage');
        $category = EmployeeCategory::query()->with('breaks')->findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->fillFrom($category);
        $this->showForm = true;
    }

    public function addBreakRow(): void
    {
        $this->authorize('org.manage');
        $this->breaks[] = [
            'break_type_id' => '',
            'start_time' => '',
            'end_time' => '',
            'duration_minutes' => '',
        ];
    }

    public function removeBreakRow(int $index): void
    {
        $this->authorize('org.manage');
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    public function updated(string $property): void
    {
        if (! preg_match('/^breaks\.(\d+)\.(start_time|end_time)$/', $property, $matches)) {
            return;
        }

        $index = (int) $matches[1];
        $row = $this->breaks[$index] ?? null;
        if ($row === null || $row['start_time'] === '' || $row['end_time'] === '') {
            return;
        }

        try {
            $start = CarbonImmutable::createFromFormat('H:i', $row['start_time']);
            $end = CarbonImmutable::createFromFormat('H:i', $row['end_time']);
        } catch (\Throwable) {
            return;
        }

        $minutes = (int) $start->diffInMinutes($end, false);
        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        $this->breaks[$index]['duration_minutes'] = (string) $minutes;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetFormFields();
    }

    public function save(EmployeeCategoryService $categories): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate($this->rules());

        $payload = $this->toPayload($validated);

        try {
            if ($this->editingId === null) {
                $categories->create($payload);
            } else {
                $category = EmployeeCategory::query()->findOrFail($this->editingId);
                $categories->update($category, $payload);
            }
        } catch (DutyEffectiveFromRequired $e) {
            $this->addError('duty_effective_from', $e->getMessage());

            return;
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function toggleActive(int $categoryId): void
    {
        $this->authorize('org.manage');
        $category = EmployeeCategory::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = EmployeeCategory::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        $dutyHistory = [];
        if ($this->editingId !== null) {
            $dutyHistory = EmployeeCategory::query()
                ->findOrFail($this->editingId)
                ->dutySchedules()
                ->orderByDesc('effective_from')
                ->get();
        }

        return view('organisation::livewire.employee-categories-index', [
            'categories' => $rows,
            'breakTypes' => BreakType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'dutyHistory' => $dutyHistory,
            'dutyFieldsDirty' => $this->dutyFieldsDirty(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Employee categories',
            'moduleNav' => OrganisationNav::items('categories'),
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        $optionalInteger = fn (string $field) => $this->{$field} === ''
            ? ['nullable']
            : ['integer', 'min:0', 'max:365'];
        $optionalNumeric = fn (string $field) => $this->{$field} === ''
            ? ['nullable']
            : ['numeric', 'min:0'];
        $optionalTime = fn (string $field) => $this->{$field} === ''
            ? ['nullable']
            : ['date_format:H:i'];

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('employee_categories', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'annual_leaves' => $optionalInteger('annual_leaves'),
            'casual_leaves' => $optionalInteger('casual_leaves'),
            'medical_leaves' => $optionalInteger('medical_leaves'),
            'normal_ot_rate' => $optionalNumeric('normal_ot_rate'),
            'double_ot_rate' => $optionalNumeric('double_ot_rate'),
            'no_pay_rate_per_hour' => $optionalNumeric('no_pay_rate_per_hour'),
            'no_pay_rate_per_day' => $optionalNumeric('no_pay_rate_per_day'),
            'saturday_rate' => $optionalNumeric('saturday_rate'),
            'sunday_rate' => $optionalNumeric('sunday_rate'),
            'payroll_workdays' => ['required', 'integer', 'min:0', 'max:31'],
            'payroll_workhours' => ['required', 'integer', 'min:0', 'max:24'],
            'saturday_ot_mode' => ['required', 'integer', 'in:0,1,2'],
            'custom_saturday_ot_type' => $optionalNumeric('custom_saturday_ot_type'),
            'sunday_ot_mode' => ['required', 'integer', 'in:0,1,2'],
            'custom_sunday_ot_type' => $optionalNumeric('custom_sunday_ot_type'),
            'on_duty_time' => $optionalTime('on_duty_time'),
            'off_duty_time' => $optionalTime('off_duty_time'),
            'morning_short_beginning' => $optionalTime('morning_short_beginning'),
            'morning_short_ending' => $optionalTime('morning_short_ending'),
            'evening_short_beginning' => $optionalTime('evening_short_beginning'),
            'evening_short_ending' => $optionalTime('evening_short_ending'),
            'halfday_beginning' => $optionalTime('halfday_beginning'),
            'late_on' => $optionalTime('late_on'),
            'duty_effective_from' => $this->editingId !== null && $this->dutyFieldsDirty()
                ? ['required', 'date']
                : ['nullable', 'date'],
            'breaks' => ['array'],
            'breaks.*.break_type_id' => ['required', 'integer', Rule::exists('break_types', 'id')->where('is_active', true)],
            'breaks.*.start_time' => ['required', 'date_format:H:i'],
            'breaks.*.end_time' => ['required', 'date_format:H:i'],
            'breaks.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function toPayload(array $validated): array
    {
        $nullable = [
            'annual_leaves', 'casual_leaves', 'medical_leaves',
            'normal_ot_rate', 'double_ot_rate', 'no_pay_rate_per_hour', 'no_pay_rate_per_day',
            'saturday_rate', 'sunday_rate', 'custom_saturday_ot_type', 'custom_sunday_ot_type',
            'on_duty_time', 'off_duty_time', 'morning_short_beginning', 'morning_short_ending',
            'evening_short_beginning', 'evening_short_ending', 'halfday_beginning', 'late_on',
        ];

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'payroll_workdays' => (int) $validated['payroll_workdays'],
            'payroll_workhours' => (int) $validated['payroll_workhours'],
            'saturday_ot_mode' => (int) $validated['saturday_ot_mode'],
            'sunday_ot_mode' => (int) $validated['sunday_ot_mode'],
        ];

        foreach ($nullable as $field) {
            $value = $validated[$field] ?? null;
            if ($value === null || $value === '') {
                $payload[$field] = null;
            } elseif (str_ends_with($field, '_time') || str_ends_with($field, '_beginning') || str_ends_with($field, '_ending') || $field === 'late_on') {
                $payload[$field] = strlen((string) $value) === 5 ? $value.':00' : $value;
            } else {
                $payload[$field] = $value;
            }
        }

        if (($validated['duty_effective_from'] ?? '') !== '') {
            $payload['duty_effective_from'] = $validated['duty_effective_from'];
        }

        $payload['breaks'] = array_map(function (array $row) {
            return [
                'break_type_id' => (int) $row['break_type_id'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'duration_minutes' => (int) $row['duration_minutes'],
            ];
        }, $validated['breaks'] ?? []);

        return $payload;
    }

    private function fillFrom(EmployeeCategory $category): void
    {
        $this->code = $category->code;
        $this->name = $category->name;
        $this->annual_leaves = $category->annual_leaves !== null ? (string) $category->annual_leaves : '';
        $this->casual_leaves = $category->casual_leaves !== null ? (string) $category->casual_leaves : '';
        $this->medical_leaves = $category->medical_leaves !== null ? (string) $category->medical_leaves : '';
        $this->normal_ot_rate = $category->normal_ot_rate !== null ? (string) $category->normal_ot_rate : '';
        $this->double_ot_rate = $category->double_ot_rate !== null ? (string) $category->double_ot_rate : '';
        $this->no_pay_rate_per_hour = $category->no_pay_rate_per_hour !== null ? (string) $category->no_pay_rate_per_hour : '';
        $this->no_pay_rate_per_day = $category->no_pay_rate_per_day !== null ? (string) $category->no_pay_rate_per_day : '';
        $this->saturday_rate = $category->saturday_rate !== null ? (string) $category->saturday_rate : '';
        $this->sunday_rate = $category->sunday_rate !== null ? (string) $category->sunday_rate : '';
        $this->payroll_workdays = (string) $category->payroll_workdays;
        $this->payroll_workhours = (string) $category->payroll_workhours;
        $this->saturday_ot_mode = (string) $category->saturday_ot_mode;
        $this->custom_saturday_ot_type = $category->custom_saturday_ot_type !== null ? (string) $category->custom_saturday_ot_type : '';
        $this->sunday_ot_mode = (string) $category->sunday_ot_mode;
        $this->custom_sunday_ot_type = $category->custom_sunday_ot_type !== null ? (string) $category->custom_sunday_ot_type : '';
        $this->on_duty_time = $this->formatTime($category->on_duty_time);
        $this->off_duty_time = $this->formatTime($category->off_duty_time);
        $this->morning_short_beginning = $this->formatTime($category->morning_short_beginning);
        $this->morning_short_ending = $this->formatTime($category->morning_short_ending);
        $this->evening_short_beginning = $this->formatTime($category->evening_short_beginning);
        $this->evening_short_ending = $this->formatTime($category->evening_short_ending);
        $this->halfday_beginning = $this->formatTime($category->halfday_beginning);
        $this->late_on = $this->formatTime($category->late_on);
        $this->duty_effective_from = '';
        $this->loadedDutyTimes = [
            'on_duty_time' => $this->on_duty_time,
            'off_duty_time' => $this->off_duty_time,
            'morning_short_beginning' => $this->morning_short_beginning,
            'morning_short_ending' => $this->morning_short_ending,
            'evening_short_beginning' => $this->evening_short_beginning,
            'evening_short_ending' => $this->evening_short_ending,
            'halfday_beginning' => $this->halfday_beginning,
            'late_on' => $this->late_on,
        ];
        $this->breaks = $category->breaks->map(fn ($break) => [
            'break_type_id' => (string) $break->break_type_id,
            'start_time' => $this->formatTime($break->start_time),
            'end_time' => $this->formatTime($break->end_time),
            'duration_minutes' => (string) $break->duration_minutes,
        ])->values()->all();
    }

    private function dutyFieldsDirty(): bool
    {
        if ($this->editingId === null || $this->loadedDutyTimes === []) {
            return false;
        }

        foreach ($this->loadedDutyTimes as $field => $value) {
            if ($this->{$field} !== $value) {
                return true;
            }
        }

        return false;
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = (string) $value;

        return strlen($string) >= 5 ? substr($string, 0, 5) : $string;
    }

    private function resetFormFields(): void
    {
        $this->resetValidation();
        $this->code = '';
        $this->name = '';
        $this->annual_leaves = '';
        $this->casual_leaves = '';
        $this->medical_leaves = '';
        $this->normal_ot_rate = '';
        $this->double_ot_rate = '';
        $this->no_pay_rate_per_hour = '';
        $this->no_pay_rate_per_day = '';
        $this->saturday_rate = '';
        $this->sunday_rate = '';
        $this->payroll_workdays = '0';
        $this->payroll_workhours = '0';
        $this->saturday_ot_mode = '0';
        $this->custom_saturday_ot_type = '';
        $this->sunday_ot_mode = '0';
        $this->custom_sunday_ot_type = '';
        $this->on_duty_time = '';
        $this->off_duty_time = '';
        $this->morning_short_beginning = '';
        $this->morning_short_ending = '';
        $this->evening_short_beginning = '';
        $this->evening_short_ending = '';
        $this->halfday_beginning = '';
        $this->late_on = '';
        $this->duty_effective_from = '';
        $this->breaks = [];
        $this->loadedDutyTimes = [];
    }
}
