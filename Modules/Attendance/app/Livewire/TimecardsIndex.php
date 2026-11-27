<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\ManualTimecardService;
use Modules\Attendance\Support\AttendanceNav;

class TimecardsIndex extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formEmployeeNo = '';

    public string $formWorkDate = '';

    public string $formDayType = 'FULL';

    public string $formDayValue = '1';

    public string $formFirstIn = '';

    public string $formLastOut = '';

    public string $formPunchCount = '0';

    public string $formLate = '0';

    public string $formEarly = '0';

    public string $formOtMorning = '0';

    public string $formOtEvening = '0';

    public bool $formShortLeave = false;

    public bool $formHoliday = false;

    public string $formException = '';

    public string $formError = '';

    public string $listError = '';

    public function mount(): void
    {
        $this->authorize('timecard.view');

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $this->from = $today;
        $this->to = $today;
    }

    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'paginators') && ! str_starts_with($name, 'form')) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('timecard.view');

        return view('attendance::livewire.timecards-index', [
            'timecards' => $this->query()->paginate(25),
            'canAdjust' => auth()->user()?->can('attendance.adjust') ?? false,
            'dayTypes' => DayType::cases(),
            'exceptions' => TimecardException::cases(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Timecards',
            'moduleNav' => AttendanceNav::items('timecards'),
        ]);
    }

    public function openCreate(): void
    {
        $this->authorize('attendance.adjust');
        $this->resetForm();
        $this->formWorkDate = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('attendance.adjust');
        $card = Timecard::query()->with('employment')->findOrFail($id);
        $zone = config('app.timezone');

        $this->editingId = $card->id;
        $this->formEmployeeNo = (string) $card->employment?->employee_no;
        $this->formWorkDate = $card->work_date->toDateString();
        $this->formDayType = $card->day_type->value;
        $this->formDayValue = (string) $card->day_value;
        $this->formFirstIn = $card->first_in?->timezone($zone)->format('H:i:s') ?? '';
        $this->formLastOut = $card->last_out?->timezone($zone)->format('H:i:s') ?? '';
        $this->formPunchCount = (string) $card->punch_count;
        $this->formLate = (string) $card->late_in_minutes;
        $this->formEarly = (string) $card->early_out_minutes;
        $this->formOtMorning = (string) $card->ot_morning_minutes;
        $this->formOtEvening = (string) $card->ot_evening_minutes;
        $this->formShortLeave = (bool) $card->short_leave;
        $this->formHoliday = (bool) $card->is_holiday;
        $this->formException = $card->exception?->value ?? '';
        $this->formError = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function saveTimecard(ManualTimecardService $manual): void
    {
        $this->authorize('attendance.adjust');
        $this->formError = '';

        $validated = $this->validate([
            'formEmployeeNo' => ['required', 'string', 'max:50'],
            'formWorkDate' => ['required', 'date_format:Y-m-d'],
            'formDayType' => ['required', 'in:FULL,HALF,SHORT,NONE'],
            'formDayValue' => ['required', 'numeric', 'between:0,9.99'],
            'formFirstIn' => ['nullable', 'regex:/^$|^\d{2}:\d{2}(:\d{2})?$/'],
            'formLastOut' => ['nullable', 'regex:/^$|^\d{2}:\d{2}(:\d{2})?$/'],
            'formPunchCount' => ['required', 'integer', 'min:0', 'max:65535'],
            'formLate' => ['required', 'integer', 'min:0', 'max:65535'],
            'formEarly' => ['required', 'integer', 'min:0', 'max:65535'],
            'formOtMorning' => ['required', 'integer', 'min:0', 'max:65535'],
            'formOtEvening' => ['required', 'integer', 'min:0', 'max:65535'],
            'formShortLeave' => ['boolean'],
            'formHoliday' => ['boolean'],
            'formException' => ['nullable', Rule::in(['', 'MISSING_PUNCH', 'SHORT_PRESENCE', 'NO_PROFILE'])],
        ]);

        $fields = [
            'work_date' => $validated['formWorkDate'],
            'day_type' => $validated['formDayType'],
            'day_value' => number_format((float) $validated['formDayValue'], 2, '.', ''),
            'first_in' => $this->at($validated['formWorkDate'], $validated['formFirstIn'] ?? null),
            'last_out' => $this->at($validated['formWorkDate'], $validated['formLastOut'] ?? null),
            'punch_count' => (int) $validated['formPunchCount'],
            'late_in_minutes' => (int) $validated['formLate'],
            'early_out_minutes' => (int) $validated['formEarly'],
            'ot_morning_minutes' => (int) $validated['formOtMorning'],
            'ot_evening_minutes' => (int) $validated['formOtEvening'],
            'short_leave' => (bool) $validated['formShortLeave'],
            'is_holiday' => (bool) $validated['formHoliday'],
            'exception' => ($validated['formException'] ?? '') !== '' ? $validated['formException'] : null,
        ];

        try {
            if ($this->editingId === null) {
                $manual->create($validated['formEmployeeNo'], $fields);
            } else {
                $card = Timecard::query()->findOrFail($this->editingId);
                $manual->update($card, $validated['formEmployeeNo'], $fields);
            }
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteTimecard(int $id, ManualTimecardService $manual): void
    {
        $this->authorize('attendance.adjust');
        $this->listError = '';
        $card = Timecard::query()->findOrFail($id);

        try {
            $manual->delete($card);
        } catch (DomainException $e) {
            $this->listError = $e->getMessage();
        }
    }

    private function query(): Builder
    {
        [$from, $to] = $this->bounds();

        $query = Timecard::query()
            ->with(['employment.person'])
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('employment', function (Builder $employment) use ($like): void {
                $employment->where('employee_no', 'like', $like)
                    ->orWhereHas('person', fn (Builder $person) => $person->where('name_with_initials', 'like', $like));
            });
        }

        return $query->orderByDesc('work_date')->orderByDesc('id');
    }

    /** @return array{0: string, 1: string} */
    private function bounds(): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $from = $this->validDate($this->from) ?? $today;
        $to = $this->validDate($this->to) ?? $today;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function validDate(string $value): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    private function at(string $date, mixed $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }

        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return $date.' '.$time;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formEmployeeNo = '';
        $this->formWorkDate = '';
        $this->formDayType = 'FULL';
        $this->formDayValue = '1';
        $this->formFirstIn = '';
        $this->formLastOut = '';
        $this->formPunchCount = '0';
        $this->formLate = '0';
        $this->formEarly = '0';
        $this->formOtMorning = '0';
        $this->formOtEvening = '0';
        $this->formShortLeave = false;
        $this->formHoliday = false;
        $this->formException = '';
        $this->formError = '';
        $this->resetValidation();
    }
}
