<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Services\JobService;
use Modules\Organisation\Support\OrganisationNav;

class JobsIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->showForm = true;
    }

    public function openEdit(int $jobId): void
    {
        $this->authorize('org.manage');
        $job = Job::query()->findOrFail($jobId);
        $this->editingId = $job->id;
        $this->code = $job->code;
        $this->name = $job->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
    }

    public function save(JobService $jobs): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('hr_jobs', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
        ]);

        try {
            if ($this->editingId === null) {
                $jobs->create($validated);
            } else {
                $job = Job::query()->findOrFail($this->editingId);
                $jobs->update($job, $validated);
            }
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function toggleActive(int $jobId): void
    {
        $this->authorize('org.manage');
        $job = Job::query()->findOrFail($jobId);
        $job->update(['is_active' => ! $job->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = Job::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('organisation::livewire.jobs-index', [
            'jobs' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Jobs',
            'moduleNav' => OrganisationNav::items('jobs'),
        ]);
    }
}
