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
    public bool $showInactive = false;

    /** @var array<int, bool> */
    public array $expanded = [];

    public bool $showForm = false;

    public string $formMode = 'create';

    public ?int $editingId = null;

    public ?int $parentId = null;

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
        $this->showForm = true;
    }

    public function openAddChild(int $parentId): void
    {
        $this->authorize('org.manage');
        $parent = OrgUnit::query()->findOrFail($parentId);
        $this->resetFormState();
        $this->formMode = 'create';
        $this->parentId = $parent->id;
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
        $unit = OrgUnit::query()->findOrFail($unitId);
        $this->resetFormState();
        $this->formMode = 'edit';
        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->type = $unit->type->value;
        $this->parentId = $unit->parent_id;
        $this->showForm = true;
    }

    public function openMove(int $unitId): void
    {
        $this->authorize('org.manage');
        $unit = OrgUnit::query()->findOrFail($unitId);
        $this->resetFormState();
        $this->formMode = 'move';
        $this->editingId = $unit->id;
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
        $this->code = '';
        $this->name = '';
        $this->type = '';
        $this->moveParentId = null;
        $this->resetErrorBag();
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
                ->orderBy('code')
                ->get();

            $tree = $this->buildTree($all);
            $moveParents = $all->where('id', '!=', $this->editingId);
        }

        return view('organisation::livewire.org-tree', [
            'companySelected' => $companyId !== null,
            'tree' => $tree,
            'moveParents' => $moveParents,
            'types' => OrgUnitType::cases(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Org units',
            'moduleNav' => OrganisationNav::items('units'),
        ]);
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

                if ($hasChildren && ($this->expanded[$unit->id] ?? true)) {
                    $walk($unit->id, $depth + 1);
                }
            }
        };

        $walk(null, 0);

        return $rows;
    }
}
