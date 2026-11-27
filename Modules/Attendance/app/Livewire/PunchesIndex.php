<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\ManualPunchService;
use Modules\Attendance\Support\AttendanceNav;

class PunchesIndex extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public string $deviceId = '';

    public string $resolution = '';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formDeviceId = '';

    public string $formDeviceUserId = '';

    public string $formDate = '';

    public string $formTime = '';

    public ?string $formVerifyMode = null;

    public ?string $formState = null;

    public string $formError = '';

    public function mount(): void
    {
        $this->authorize('attendance.punch.view');

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $this->from = $today;
        $this->to = $today;
    }

    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'paginators')) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('attendance.punch.view');

        return view('attendance::livewire.punches-index', [
            'punches' => $this->query()->paginate(25),
            'devices' => Device::query()->orderBy('name')->get(['id', 'name']),
            'canAdjust' => auth()->user()?->can('attendance.adjust') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Punches',
            'moduleNav' => AttendanceNav::items('punches'),
        ]);
    }

    private function query(): Builder
    {
        [$from, $to] = $this->bounds();

        $query = Punch::query()
            ->with(['device', 'employment.person'])
            ->whereBetween('punched_at', [$from, $to])
            ->where(function (Builder $scoped): void {
                $scoped->whereHas('employment')->orWhereNull('employment_id');
            });

        if ($this->deviceId !== '' && ctype_digit($this->deviceId)) {
            $query->where('device_id', (int) $this->deviceId);
        }

        $resolution = PunchResolution::tryFrom($this->resolution);
        if ($resolution !== null) {
            $query->where('resolution', $resolution);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $match) use ($term, $like): void {
                $matchedId = false;
                if (ctype_digit($term)) {
                    $match->where('device_user_id', (int) $term);
                    $matchedId = true;
                }

                $byEmployee = function (Builder $employment) use ($like): void {
                    $employment->where('employee_no', 'like', $like)
                        ->orWhereHas('person', fn (Builder $person) => $person->where('name_with_initials', 'like', $like));
                };

                if ($matchedId) {
                    $match->orWhereHas('employment', $byEmployee);
                } else {
                    $match->whereHas('employment', $byEmployee);
                }
            });
        }

        return $query->orderByDesc('punched_at')->orderByDesc('id');
    }

    public function openCreate(): void
    {
        $this->authorize('attendance.adjust');
        $this->resetForm();
        $today = CarbonImmutable::now(config('app.timezone'));
        $this->formDate = $today->toDateString();
        $this->formTime = $today->format('H:i:s');
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('attendance.adjust');
        $punch = Punch::query()->findOrFail($id);
        $at = $punch->punched_at->timezone(config('app.timezone'));

        $this->editingId = $punch->id;
        $this->formDeviceId = (string) $punch->device_id;
        $this->formDeviceUserId = (string) $punch->device_user_id;
        $this->formDate = $at->format('Y-m-d');
        $this->formTime = $at->format('H:i:s');
        $this->formVerifyMode = $punch->verify_mode === null ? '' : (string) $punch->verify_mode;
        $this->formState = $punch->raw_state === null ? '' : (string) $punch->raw_state;
        $this->formError = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function savePunch(ManualPunchService $manual): void
    {
        $this->authorize('attendance.adjust');
        $this->formError = '';
        $this->formVerifyMode = $this->formVerifyMode === '' ? null : $this->formVerifyMode;
        $this->formState = $this->formState === '' ? null : $this->formState;

        $validated = $this->validate([
            'formDeviceId' => ['required', 'integer', 'exists:devices,id'],
            'formDeviceUserId' => ['required', 'integer', 'min:1'],
            'formDate' => ['required', 'date_format:Y-m-d'],
            'formTime' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'formVerifyMode' => ['nullable', 'integer', 'between:0,255'],
            'formState' => ['nullable', 'integer', 'between:0,255'],
        ]);

        $device = Device::query()->findOrFail((int) $validated['formDeviceId']);
        $punchedAt = $validated['formDate'].' '.$this->withSeconds($validated['formTime']);
        $verifyMode = $this->optionalByte($validated['formVerifyMode'] ?? null);
        $state = $this->optionalByte($validated['formState'] ?? null);
        $deviceUserId = (int) $validated['formDeviceUserId'];

        try {
            if ($this->editingId === null) {
                $manual->create($device, $deviceUserId, $punchedAt, $verifyMode, $state);
            } else {
                $punch = Punch::query()->findOrFail($this->editingId);
                $manual->update($punch, $device, $deviceUserId, $punchedAt, $verifyMode, $state);
            }
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function deletePunch(int $id, ManualPunchService $manual): void
    {
        $this->authorize('attendance.adjust');
        $punch = Punch::query()->findOrFail($id);
        $manual->delete($punch);
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

        return [$from.' 00:00:00', $to.' 23:59:59'];
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

    private function withSeconds(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private function optionalByte(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formDeviceId = '';
        $this->formDeviceUserId = '';
        $this->formDate = '';
        $this->formTime = '';
        $this->formVerifyMode = '';
        $this->formState = '';
        $this->formError = '';
        $this->resetValidation();
    }
}
