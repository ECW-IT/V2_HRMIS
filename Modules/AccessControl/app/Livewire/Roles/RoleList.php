<?php

namespace Modules\AccessControl\Livewire\Roles;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RoleList extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formName = '';

    public function openCreate(): void
    {
        $this->authorize('access-control.manage');
        $this->editingId = null;
        $this->formName = '';
        $this->showForm = true;
    }

    public function openEdit(int $roleId): void
    {
        $this->authorize('access-control.manage');
        $role = Role::query()->findOrFail($roleId);
        $this->editingId = $role->id;
        $this->formName = $role->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formName = '';
    }

    public function save(): void
    {
        $this->authorize('access-control.manage');

        $validated = $this->validate([
            'formName' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->ignore($this->editingId),
            ],
        ], [
            'formName.regex' => 'Use a lower-case slug such as hr-officer.',
        ]);

        if ($this->editingId === null) {
            Role::create([
                'name' => $validated['formName'],
                'guard_name' => 'web',
            ]);
        } else {
            $role = Role::query()->findOrFail($this->editingId);
            $role->update(['name' => $validated['formName']]);
        }

        $this->closeForm();
    }

    public function delete(int $roleId): void
    {
        $this->authorize('access-control.manage');

        $role = Role::query()->findOrFail($roleId);
        $userCount = User::role($role->name)->count();

        if ($userCount > 0) {
            $this->addError('delete', 'This role still has users assigned. Reassign them before deleting.');

            return;
        }

        $role->delete();
    }

    public function render(): View
    {
        $this->authorize('access-control.view');

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->get();

        return view('accesscontrol::livewire.roles.role-list', [
            'roles' => $roles,
            'canManage' => Auth::user()?->can('access-control.manage') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Access control',
            'title' => 'Roles',
            'moduleNav' => $this->moduleNav('roles'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function moduleNav(string $active): array
    {
        return [
            [
                'label' => 'User account',
                'route' => route('access-control.users.index'),
                'active' => $active === 'users',
            ],
            [
                'label' => 'Role',
                'route' => route('access-control.roles.index'),
                'active' => $active === 'roles',
            ],
            [
                'label' => 'Permission',
                'route' => route('access-control.roles.index'),
                'active' => $active === 'permissions',
            ],
            [
                'label' => 'Audit',
                'route' => route('access-control.audit.index'),
                'active' => $active === 'audit',
            ],
        ];
    }
}
