<?php

namespace Modules\Workflow\Livewire;

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

        return view('workflow::livewire.my-approvals', [
            'waiting' => $waiting,
            'mine' => $mine,
            'selected' => $selected,
            'reviewRows' => $reviewRows,
            'hideCompensationPayload' => $hideCompensationPayload,
            'summaries' => $summaries,
        ])->layout('components.layouts.app', [
            'title' => 'My approvals',
        ]);
    }
}
