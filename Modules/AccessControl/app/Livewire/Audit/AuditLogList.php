<?php

namespace Modules\AccessControl\Livewire\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogList extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $userId = '';

    public string $auditableType = '';

    public string $event = '';

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['dateFrom', 'dateTo', 'userId', 'auditableType', 'event'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->userId !== '', fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->auditableType !== '', fn ($q) => $q->where('auditable_type', $this->auditableType))
            ->when($this->event !== '', fn ($q) => $q->where('event', $this->event))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        $types = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        return view('accesscontrol::livewire.audit.audit-log-list', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'types' => $types,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Access control',
            'title' => 'Audit',
            'moduleNav' => [
                [
                    'label' => 'User account',
                    'route' => route('access-control.users.index'),
                    'active' => false,
                ],
                [
                    'label' => 'Role',
                    'route' => route('access-control.roles.index'),
                    'active' => false,
                ],
                [
                    'label' => 'Audit',
                    'route' => route('access-control.audit.index'),
                    'active' => true,
                ],
            ],
        ]);
    }
}
