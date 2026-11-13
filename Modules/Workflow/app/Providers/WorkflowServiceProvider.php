<?php

namespace Modules\Workflow\Providers;

use Livewire\Livewire;
use Modules\Workflow\Livewire\MyApprovals;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WorkflowServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Workflow';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'workflow';

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

        Livewire::component('workflow.my-approvals', MyApprovals::class);
    }
}
