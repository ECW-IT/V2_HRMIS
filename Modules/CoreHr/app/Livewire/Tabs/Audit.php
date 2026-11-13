<?php

namespace Modules\CoreHr\Livewire\Tabs;

use App\Models\AuditLog;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;

class Audit extends Component
{
    use WithPagination;

    public Employment $employment;

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
    }

    public function render(): View
    {
        $this->authorize('audit.view');

        $assignmentIds = Assignment::query()->where('employment_id', $this->employment->id)->pluck('id')->all();
        $accountIds = EmployeeBankAccount::query()->where('employment_id', $this->employment->id)->pluck('id')->all();

        // Account numbers are stored masked in the audit log, so nothing needs hiding here.
        $logs = AuditLog::query()
            ->with('user')
            ->where(function ($query) use ($assignmentIds, $accountIds) {
                $query->where(fn ($q) => $q->where('auditable_type', Employment::class)->where('auditable_id', $this->employment->id))
                    ->orWhere(fn ($q) => $q->where('auditable_type', Person::class)->where('auditable_id', $this->employment->person_id))
                    ->orWhere(fn ($q) => $q->where('auditable_type', Assignment::class)->whereIn('auditable_id', $assignmentIds))
                    ->orWhere(fn ($q) => $q->where('auditable_type', EmployeeBankAccount::class)->whereIn('auditable_id', $accountIds));
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('corehr::livewire.tabs.audit', ['logs' => $logs]);
    }
}
