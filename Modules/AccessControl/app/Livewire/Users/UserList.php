<?php

namespace Modules\AccessControl\Livewire\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Role;

class UserList extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formName = '';

    public string $formEmail = '';

    public string $formPassword = '';

    public bool $formAllCompanies = false;

    /** @var array<int, string> */
    public array $formCompanyIds = [];

    /** @var array<int, string> */
    public array $formRoles = [];

    public bool $formIsActive = true;

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::query()->with(['companies', 'roles'])->findOrFail($userId);
        $this->editingId = $user->id;
        $this->formName = $user->name;
        $this->formEmail = $user->email;
        $this->formPassword = '';
        $this->formAllCompanies = (bool) $user->all_companies;
        $this->formCompanyIds = $user->companies->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->formRoles = $user->roles->pluck('name')->all();
        $this->formIsActive = (bool) $user->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->formName = '';
        $this->formEmail = '';
        $this->formPassword = '';
        $this->formAllCompanies = false;
        $this->formCompanyIds = [];
        $this->formRoles = [];
        $this->formIsActive = true;
    }

    public function save(): void
    {
        $this->authorize('access-control.manage');

        $rules = [
            'formName' => ['required', 'string', 'max:255'],
            'formEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'formRoles' => ['required', 'array', 'min:1'],
            'formIsActive' => ['boolean'],
            'formAllCompanies' => ['boolean'],
            'formCompanyIds' => [$this->formAllCompanies ? 'nullable' : 'required', 'array'],
        ];

        if ($this->editingId === null) {
            $rules['formPassword'] = ['required', 'string', 'min:10'];
        } elseif ($this->formPassword !== '') {
            $rules['formPassword'] = ['string', 'min:10'];
        }

        $this->validate($rules);

        DB::transaction(function () {
            $data = [
                'name' => $this->formName,
                'email' => $this->formEmail,
                'is_active' => $this->formIsActive,
                'all_companies' => $this->formAllCompanies,
            ];

            if ($this->editingId === null) {
                $data['password'] = $this->formPassword;
                $data['must_change_password'] = true;
                $user = User::query()->create($data);
                $oldRoles = [];
                $oldCompanies = [];
            } else {
                $user = User::query()->with(['roles', 'companies'])->findOrFail($this->editingId);
                $this->guardSelfAccess($user);

                $oldRoles = $user->roles->pluck('name')->all();
                $oldCompanies = $user->all_companies
                    ? ['*']
                    : $user->companies->pluck('code')->all();

                if ($this->formPassword !== '') {
                    $data['password'] = $this->formPassword;
                    $data['must_change_password'] = true;
                }
                $user->update($data);
            }

            $user->syncRoles($this->formRoles);
            $user->companies()->sync($this->formAllCompanies ? [] : $this->formCompanyIds);

            $user->load(['roles', 'companies']);
            $newRoles = $user->roles->pluck('name')->all();
            $newCompanies = $user->all_companies
                ? ['*']
                : $user->companies->pluck('code')->all();

            $this->writeListAudit($user, 'roles', $oldRoles, $newRoles);
            $this->writeListAudit($user, 'companies', $oldCompanies, $newCompanies);
        });

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('access-control.view');

        $users = User::query()
            ->with(['roles', 'companies'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return view('accesscontrol::livewire.users.user-list', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => Auth::user()?->can('access-control.manage') ?? false,
            'moduleNav' => $this->moduleNav('users'),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Access control',
            'title' => 'Users',
            'moduleNav' => $this->moduleNav('users'),
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

    private function guardSelfAccess(User $user): void
    {
        if ((int) $user->id !== (int) Auth::id()) {
            return;
        }

        if (! $this->formIsActive) {
            throw new AuthorizationException('You cannot remove your own access.');
        }

        if ($user->hasRole('super-admin') && ! in_array('super-admin', $this->formRoles, true)) {
            throw new AuthorizationException('You cannot remove your own access.');
        }
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     */
    private function writeListAudit(User $user, string $key, array $old, array $new): void
    {
        $oldSorted = $old;
        $newSorted = $new;
        sort($oldSorted);
        sort($newSorted);

        if ($oldSorted === $newSorted) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'company_id' => null,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'UPDATED',
            'old_values' => [$key => array_values($oldSorted)],
            'new_values' => [$key => array_values($newSorted)],
            'reason' => AuditLogger::currentReason(),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
