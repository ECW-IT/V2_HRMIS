<?php

namespace Modules\Workflow\Livewire;

use App\Exceptions\Domain\DomainException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Modules\Workflow\Support\ApprovalReviewPresenter;

class MyApprovals extends Component
{
    public string $tab = 'waiting';

    public ?int $selectedId = null;

    public string $remarks = '';

    /** @var list<int|string> */
    public array $selectedIds = [];

    public string $notice = '';

    public function updatedTab(): void
    {
        $this->selectedIds = [];
        $this->notice = '';
    }

    public function open(int $requestId): void
    {
        $this->selectedId = $requestId;
        $this->remarks = '';
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->remarks = '';
        $this->resetErrorBag();
    }

    public function approve(ApprovalService $approvals): void
    {
        $this->authorize('approval.act');
        $request = ApprovalRequest::query()->findOrFail($this->selectedId);
        $approvals->approve($request, auth()->user(), $this->remarks !== '' ? $this->remarks : null);
        $this->close();
    }

    public function reject(ApprovalService $approvals): void
    {
        $this->authorize('approval.act');
        $this->validate([
            'remarks' => ['required', 'string', 'min:1'],
        ]);

        $request = ApprovalRequest::query()->findOrFail($this->selectedId);
        $approvals->reject($request, auth()->user(), $this->remarks);
        $this->close();
    }

    public function cancel(int $requestId, ApprovalService $approvals): void
    {
        $this->authorize('approval.act');
        $request = ApprovalRequest::query()->findOrFail($requestId);
        $approvals->cancel($request, auth()->user());
    }

    public function toggleAll(ApprovalService $approvals): void
    {
        $this->authorize('approval.act');
        $waitingIds = $approvals->pendingFor(auth()->user())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $waitingIds !== [] && count(array_intersect($selected, $waitingIds)) === count($waitingIds);
        $this->selectedIds = $allSelected ? [] : $waitingIds;
    }

    public function approveSelected(ApprovalService $approvals): void
    {
        $this->authorize('approval.act');

        $ids = array_values(array_unique(array_map('intval', $this->selectedIds)));

        if ($ids === []) {
            $this->notice = 'Tick at least one row.';

            return;
        }

        $approved = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $request = ApprovalRequest::query()->find($id);

            if ($request === null) {
                $skipped++;

                continue;
            }

            try {
                $approvals->approve($request, auth()->user());
                $approved++;
            } catch (DomainException) {
                $skipped++;
            }
        }

        $this->selectedIds = [];
        $this->notice = "Approved {$approved}, skipped {$skipped}.";
    }

    public function render(ApprovalService $approvals): View
    {
        $this->authorize('approval.act');

        $user = auth()->user();
        $waiting = $approvals->pendingFor($user);
        $mine = ApprovalRequest::query()
            ->with(['definition', 'company'])
            ->where('requested_by', $user->id)
            ->orderByDesc('requested_at')
            ->get();

        $selected = $this->selectedId
            ? ApprovalRequest::query()->with(['definition', 'company', 'requester'])->find($this->selectedId)
            : null;

        $hideCompensationPayload = $selected !== null
            && $selected->definition?->code === 'COMPENSATION_CHANGE'
            && ! $user->can('compensation.view');

        $revealBankAccount = $user->can('employee.bank.view');
        $canViewCompensation = $user->can('compensation.view');

        $reviewRows = ($selected !== null && ! $hideCompensationPayload)
            ? ApprovalReviewPresenter::rows($selected, $revealBankAccount)
            : [];

        $summaries = [];
        foreach ($waiting->concat($mine)->unique('id') as $request) {
            $summaries[$request->id] = ApprovalReviewPresenter::listSummary(
                $request,
                $revealBankAccount,
                $request->definition?->code === 'COMPENSATION_CHANGE' && ! $canViewCompensation,
            );
        }

        $waitingIds = $waiting->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedInts = array_map('intval', $this->selectedIds);
        $allSelected = $waitingIds !== []
            && count(array_intersect($selectedInts, $waitingIds)) === count($waitingIds);

        return view('workflow::livewire.my-approvals', [
            'waiting' => $waiting,
            'mine' => $mine,
            'selected' => $selected,
            'reviewRows' => $reviewRows,
            'hideCompensationPayload' => $hideCompensationPayload,
            'summaries' => $summaries,
            'allSelected' => $allSelected,
        ])->layout('components.layouts.app', [
            'title' => 'My approvals',
        ]);
    }
}
