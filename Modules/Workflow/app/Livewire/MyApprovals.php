<?php

namespace Modules\Workflow\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;

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

        $current = [];
        if ($selected !== null && $selected->subject_type && $selected->subject_id) {
            $subject = $selected->subject_type::query()->find($selected->subject_id);
            if ($subject !== null) {
                foreach (array_keys($selected->payload ?? []) as $key) {
                    $current[$key] = $subject->getAttribute($key);
                }
            }
        }

        return view('workflow::livewire.my-approvals', [
            'waiting' => $waiting,
            'mine' => $mine,
            'selected' => $selected,
            'currentValues' => $current,
        ])->layout('components.layouts.app', [
            'title' => 'My approvals',
        ]);
    }
}
