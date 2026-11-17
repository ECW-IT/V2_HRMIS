<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Modules\Organisation\Support\OrganisationNav;

class OrgTree extends Component
{
    public string $search = '';

    public bool $showInactive = false;

    /** @var array<int, bool> */
    public array $expanded = [];

    public bool $showForm = false;

    public string $formMode = 'create';

    public ?int $editingId = null;

    public ?int $parentId = null;

    public string $parentLabel = '';

    public string $code = '';

    public string $name = '';

    public string $type = '';

    public ?int $moveParentId = null;

    public function openAddDepartment(): void
    {
        $this->authorize('org.manage');
        $this->resetFormState();
        $this->formMode = 'create';
        $this->type = OrgUnitType::DEPARTMENT->value;
        $this->parentId = null;
        $this->parentLabel = '';
        $this->showForm = true;
    }

    public function openAddChild(int $parentId): void
    {
        $this->authorize('org.manage');
        $parent = OrgUnit::query()->findOrFail($parentId);
        $this->resetFormState();
        $this->formMode = 'create';
        $this->parentId = $parent->id;
        $this->parentLabel = $parent->code.' — '.$parent->name;
        $this->type = match ($parent->type) {
            OrgUnitType::DEPARTMENT => OrgUnitType::SECTION->value,
            OrgUnitType::SECTION => OrgUnitType::SUBSECTION->value,
            default => OrgUnitType::DEPARTMENT->value,
        };
        $this->expanded[$parent->id] = true;
        $this->showForm = true;
    }

    public function openEdit(int $unitId): void
    {
        $this->authorize('org.manage');
        $unit = OrgUnit::query()->with('parent')->findOrFail($unitId);
        $this->resetFormState();
        $this->formMode = 'edit';
        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->type = $unit->type->value;
        $this->parentId = $unit->parent_id;
        $this->parentLabel = $unit->parent
            ? $unit->parent->code.' — '.$unit->parent->name
            : '';
        $this->showForm = true;
    }

    public function openMove(int $unitId): void
    {
        $this->authorize('org.manage');
        $unit = OrgUnit::query()->findOrFail($unitId);
        $this->resetFormState();
        $this->formMode = 'move';
        $this->editingId = $unit->id;
        $this->type = $unit->type->value;
        $this->moveParentId = $unit->parent_id;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetFormState();
    }

    protected function resetFormState(): void
    {
        $this->editingId = null;
        $this->parentId = null;
        $this->parentLabel = '';
        $this->code = '';
        $this->name = '';
        $this->type = '';
        $this->moveParentId = null;
        $this->resetErrorBag();
    }

    public function updatedSearch(): void
    {
        if (trim($this->search) === '') {
            $this->expanded = [];
        }
    }

    public function toggleExpanded(int $unitId): void
    {
        $this->expanded[$unitId] = ! ($this->expanded[$unitId] ?? false);
    }

    public function save(OrgUnitService $units): void
    {
        $this->authorize('org.manage');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->addError('code', 'Select a company.');

            return;
        }

        try {
            if ($this->formMode === 'move') {
                $unit = OrgUnit::query()->findOrFail($this->editingId);
                $newParent = $this->moveParentId
                    ? OrgUnit::query()->findOrFail((int) $this->moveParentId)
                    : null;
                $units->move($unit, $newParent);
            } elseif ($this->formMode === 'edit') {
                $validated = $this->validate([
                    'code' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:150'],
                    'type' => ['required', Rule::enum(OrgUnitType::class)],
                ]);
                $unit = OrgUnit::query()->findOrFail($this->editingId);
                $units->update($unit, [
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'type' => OrgUnitType::from($validated['type']),
                ]);
            } else {
                $validated = $this->validate([
                    'code' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:150'],
                    'type' => ['required', Rule::enum(OrgUnitType::class)],
                ]);
                $units->create([
                    'company_id' => $companyId,
                    'parent_id' => $this->parentId,
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'type' => OrgUnitType::from($validated['type']),
                ]);
            }
        } catch (DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function deactivate(int $unitId, OrgUnitService $units): void
    {
        $this->authorize('org.manage');

        try {
            $units->deactivate(OrgUnit::query()->findOrFail($unitId));
        } catch (DomainException $e) {
            $this->addError('tree', $e->getMessage());
        }
    }

    public function activate(int $unitId): void
    {
        $this->authorize('org.manage');
        OrgUnit::query()->findOrFail($unitId)->update(['is_active' => true]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $companyId = CompanyContext::id();
        $tree = collect();
        $moveParents = collect();

        if ($companyId !== null) {
            $all = OrgUnit::query()
                ->where('company_id', $companyId)
                ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
                })
                ->orderBy('code')
                ->get();

            // When searching, include ancestors so matched leaves stay in context.
            if ($this->search !== '' && $all->isNotEmpty()) {
                $all = $this->withAncestors($all, $companyId);
                $this->expandAncestorsForSearch($all);
            }

            $tree = $this->buildTree($all);
            $moveParents = $this->eligibleMoveParents($all, $companyId);
        }

        $moveParentOptions = $moveParents
            ->map(fn (OrgUnit $parent) => [
                'id' => $parent->id,
                'label' => $parent->code.' — '.$parent->name.' ('.$parent->type->value.')',
                'search' => strtolower($parent->code.' '.$parent->name.' '.$parent->type->value),
            ])
            ->values()
            ->all();

        return view('organisation::livewire.org-tree', [
            'companySelected' => $companyId !== null,
            'tree' => $tree,
            'moveParents' => $moveParents,
            'moveParentOptions' => $moveParentOptions,
            'allowNoParent' => $this->type === OrgUnitType::DEPARTMENT->value,
            'types' => OrgUnitType::cases(),
            'formTitle' => $this->formTitle(),
            'typeLabel' => $this->typeLabel($this->type),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Department break down',
            'moduleNav' => OrganisationNav::items('units'),
        ]);
    }

    protected function formTitle(): string
    {
        if ($this->formMode === 'move') {
            return 'Move unit';
        }

        if ($this->formMode === 'edit') {
            return 'Edit '.$this->typeLabel($this->type);
        }

        return match ($this->type) {
            OrgUnitType::SECTION->value => 'Add section',
            OrgUnitType::SUBSECTION->value => 'Add sub section',
            default => 'Add department',
        };
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            OrgUnitType::DEPARTMENT->value => 'department',
            OrgUnitType::SECTION->value => 'section',
            OrgUnitType::SUBSECTION->value => 'sub section',
            default => 'unit',
        };
    }

    /**
     * @param  Collection<int, OrgUnit>  $matched
     * @return Collection<int, OrgUnit>
     */
    protected function withAncestors(Collection $matched, int $companyId): Collection
    {
        $byId = $matched->keyBy('id');
        $needed = $matched->pluck('parent_id')->filter()->unique()->values();

        while ($needed->isNotEmpty()) {
            $missing = $needed->reject(fn ($id) => $byId->has($id));
            if ($missing->isEmpty()) {
                break;
            }

            $parents = OrgUnit::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $missing)
                ->get();

            foreach ($parents as $parent) {
                $byId->put($parent->id, $parent);
            }

            $needed = $parents->pluck('parent_id')->filter()->unique()->values();
        }

        return $byId->values()->sortBy('code')->values();
    }

    /**
     * Expand every ancestor so search hits remain visible under collapsed defaults.
     *
     * @param  Collection<int, OrgUnit>  $units
     */
    protected function expandAncestorsForSearch(Collection $units): void
    {
        foreach ($units as $unit) {
            $parentId = $unit->parent_id;
            while ($parentId !== null) {
                $this->expanded[(int) $parentId] = true;
                $parent = $units->firstWhere('id', $parentId);
                $parentId = $parent?->parent_id;
            }
        }
    }

    /**
     * @param  Collection<int, OrgUnit>  $visible
     * @return Collection<int, OrgUnit>
     */
    protected function eligibleMoveParents(Collection $visible, int $companyId): Collection
    {
        if ($this->formMode !== 'move' || $this->editingId === null) {
            return collect();
        }

        $unit = OrgUnit::query()->find($this->editingId);
        if ($unit === null) {
            return collect();
        }

        $forbidden = app(OrgUnitService::class)->descendantIds($unit);

        return OrgUnit::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotIn('id', $forbidden)
            ->orderBy('type')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, OrgUnit>  $units
     * @return list<array{unit: OrgUnit, depth: int, hasChildren: bool}>
     */
    protected function buildTree(Collection $units): array
    {
        $byParent = $units->groupBy(fn (OrgUnit $unit) => $unit->parent_id ?? 0);
        $rows = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            $key = $parentId ?? 0;
            foreach ($byParent->get($key, collect()) as $unit) {
                $children = $byParent->get($unit->id, collect());
                $hasChildren = $children->isNotEmpty();
                $rows[] = [
                    'unit' => $unit,
                    'depth' => $depth,
                    'hasChildren' => $hasChildren,
                ];

                if ($hasChildren && ($this->expanded[$unit->id] ?? false)) {
                    $walk($unit->id, $depth + 1);
                }
            }
        };

        $walk(null, 0);

        return $rows;
    }
}
