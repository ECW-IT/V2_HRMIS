<?php

namespace Modules\Organisation\Providers;

use Livewire\Livewire;
use Modules\Organisation\Livewire\BreakTypesIndex;
use Modules\Organisation\Livewire\CompaniesIndex;
use Modules\Organisation\Livewire\EmployeeCategoriesIndex;
use Modules\Organisation\Livewire\GradesIndex;
use Modules\Organisation\Livewire\JobsIndex;
use Modules\Organisation\Livewire\LocationsIndex;
use Modules\Organisation\Livewire\OrgTree;
use Modules\Organisation\Livewire\StaffWorkCategoriesIndex;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OrganisationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Organisation';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'organisation';

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

        Livewire::component('organisation.companies-index', CompaniesIndex::class);
        Livewire::component('organisation.locations-index', LocationsIndex::class);
        Livewire::component('organisation.org-tree', OrgTree::class);
        Livewire::component('organisation.jobs-index', JobsIndex::class);
        Livewire::component('organisation.employee-categories-index', EmployeeCategoriesIndex::class);
        Livewire::component('organisation.staff-work-categories-index', StaffWorkCategoriesIndex::class);
        Livewire::component('organisation.break-types-index', BreakTypesIndex::class);
        Livewire::component('organisation.grades-index', GradesIndex::class);
    }
}
