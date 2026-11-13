<?php

namespace Modules\AccessControl\Livewire\Roles;

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
        $this->authorize('access-control.view');

        $role = Role::query()->findOrFail($this->roleId);
        $valid = Permission::query()->whereIn('name', $this->selected)->pluck('name')->all();
        $role->syncPermissions($valid);

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
