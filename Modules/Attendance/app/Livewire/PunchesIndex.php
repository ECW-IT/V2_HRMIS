<?php

namespace Modules\Attendance\Livewire;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Support\AttendanceNav;

class PunchesIndex extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public string $deviceId = '';

    public string $resolution = '';

    public string $search = '';

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
}
