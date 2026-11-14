<?php

namespace Modules\AccessControl\Livewire\Roles;

use App\Models\AuditLog;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissions extends Component
{
    public int $roleId;

    public string $roleName = '';

    /** @var array<int, string> */
    public array $selected = [];

    public function mount(int $role): void
    {
        $this->authorize('access-control.view');

        $roleModel = Role::query()->findOrFail($role);
        $this->roleId = $roleModel->id;
        $this->roleName = $roleModel->name;
        $this->selected = $roleModel->permissions->pluck('name')->values()->all();
    }

    public function save(): void
    {
        $this->authorize('access-control.manage');

        $role = Role::query()->findOrFail($this->roleId);
        $old = $role->permissions->pluck('name')->all();
        $valid = Permission::query()->whereIn('name', $this->selected)->pluck('name')->all();
        $role->syncPermissions($valid);
        $new = $role->fresh()->permissions->pluck('name')->all();

        $oldSorted = $old;
        $newSorted = $new;
        sort($oldSorted);
        sort($newSorted);

        if ($oldSorted !== $newSorted) {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'company_id' => null,
                'auditable_type' => Role::class,
                'auditable_id' => $role->id,
                'event' => 'UPDATED',
                'old_values' => ['permissions' => array_values($oldSorted)],
                'new_values' => ['permissions' => array_values($newSorted)],
                'reason' => AuditLogger::currentReason(),
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        session()->flash('status', 'Permissions saved for '.$role->name.'.');
        $this->redirect(route('access-control.roles.index'), navigate: false);
    }

    public function render(): View
    {
        $permissions = Permission::query()->orderBy('name')->get();
        $groups = $permissions
            ->groupBy(fn (Permission $permission) => $this->groupKey($permission->name))
            ->sortKeys();

        return view('accesscontrol::livewire.roles.role-permissions', [
            'groups' => $groups,
            'canManage' => Auth::user()?->can('access-control.manage') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Access control',
            'title' => 'Permissions',
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
                    'label' => 'Permission',
                    'route' => route('access-control.roles.permissions', $this->roleId),
                    'active' => true,
                ],
            ],
        ]);
    }

    protected function groupKey(string $name): string
    {
        $parts = explode('.', $name);

        return $parts[0] !== '' ? $parts[0] : 'other';
    }
}
