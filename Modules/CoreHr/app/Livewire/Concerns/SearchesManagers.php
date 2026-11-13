<?php

namespace Modules\CoreHr\Livewire\Concerns;

use Illuminate\Support\Collection;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;

/** "Reports to" picker: search active employees of one company. */
trait SearchesManagers
{
    public string $managerSearch = '';

    public string|int|null $managerEmploymentId = null;

    public function pickManager(int $employmentId): void
    {
        $this->managerEmploymentId = $employmentId;
        $this->managerSearch = '';
    }

    public function clearManager(): void
    {
        $this->managerEmploymentId = null;
        $this->managerSearch = '';
    }

    /** @return Collection<int, Employment> */
    protected function managerResults(?int $companyId, ?int $excludeId = null): Collection
    {
        $term = trim($this->managerSearch);
        if ($companyId === null || $term === '') {
            return collect();
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return Employment::query()
            ->with('person')
            ->where('company_id', $companyId)
            ->where('status', EmploymentStatus::ACTIVE)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($like) {
                $q->where('employee_no', 'like', $like)
                    ->orWhereHas('person', fn ($p) => $p->where('name_with_initials', 'like', $like));
            })
            ->orderBy('employee_no')
            ->limit(10)
            ->get();
    }

    protected function selectedManager(): ?Employment
    {
        if ($this->managerEmploymentId === null || $this->managerEmploymentId === '') {
            return null;
        }

        return Employment::query()->with('person')->find((int) $this->managerEmploymentId);
    }
}
