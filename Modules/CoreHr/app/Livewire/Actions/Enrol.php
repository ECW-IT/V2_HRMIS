<?php

namespace Modules\CoreHr\Livewire\Actions;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Models\Employment;

class Enrol extends Component
{
    public Employment $employment;

    public string $deviceGroup = '';

    public string|int|null $deviceUserId = null;

    public string $from = '';

    public string $message = '';

    public string $errorMessage = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->from = max(
            CarbonImmutable::now(config('app.timezone'))->toDateString(),
            $employment->hire_date->toDateString(),
        );
    }

    public function save(EnrolmentService $enrolments): void
    {
        $this->authorize('attendance.device.manage');

        $this->message = '';
        $this->errorMessage = '';

        $this->validate([
            'deviceGroup' => ['required', 'string', 'max:20'],
            'deviceUserId' => ['required', 'integer', 'min:1'],
            'from' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $enrolments->enrol($this->employment, $this->deviceGroup, (int) $this->deviceUserId, $this->from);
        } catch (DomainException|\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->message = 'Saved.';
        $this->dispatch('employment-updated', message: $this->message);
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.enrol', [
            'deviceGroups' => Device::query()->whereNotNull('device_group')->distinct()->orderBy('device_group')->pluck('device_group'),
        ]);
    }
}
