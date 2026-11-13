<?php

namespace Modules\AccessControl\Providers;

use Livewire\Livewire;
use Modules\AccessControl\Livewire\Audit\AuditLogList;
use Modules\AccessControl\Livewire\Roles\RoleList;
use Modules\AccessControl\Livewire\Roles\RolePermissions;
use Modules\AccessControl\Livewire\Users\UserList;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AccessControlServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'AccessControl';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'accesscontrol';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('access-control.users.user-list', UserList::class);
        Livewire::component('access-control.roles.role-list', RoleList::class);
        Livewire::component('access-control.roles.role-permissions', RolePermissions::class);
        Livewire::component('access-control.audit.audit-log-list', AuditLogList::class);
    }
}
