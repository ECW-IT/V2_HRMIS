<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;

/** Submits compensation changes for approval. */
final class CompensationRequestService
{
    public function __construct(
        private readonly CompensationService $compensation,
        private readonly ApprovalService $approvals,
    ) {}

    /** $operation: 'SET_COMPENSATION' | 'ADD_COMPONENT' | 'END_COMPONENT' | 'CHANGE_COMPONENT'. Always returns the pending ApprovalRequest. */
    public function request(User $by, string $operation, Employment $e, array $data): ApprovalRequest
    {
        if (! $by->can('compensation.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($by->person_id !== null && (int) $by->person_id === (int) $e->person_id) {
            throw new AuthorizationException('You cannot change your own compensation.');
        }

        return $this->approvals->submit(
            'COMPENSATION_CHANGE',
            (int) $e->company_id,
            Employment::class,
            $e->id,
            ApprovalActionType::UPDATE,
            array_merge(['operation' => $operation], $data),
            $by,
            $e->id,
        );
    }

    /** @param  array<string, mixed>  $data */
    public function apply(string $operation, Employment $e, array $data, ?int $approvalRequestId = null): void
    {
        match ($operation) {
            'SET_COMPENSATION' => $this->compensation->set(
                $e,
                (string) $data['from'],
                $data,
                $approvalRequestId,
            ),
            'ADD_COMPONENT' => $this->compensation->addComponent(
                $e,
                (string) $data['component_code'],
                (string) $data['amount'],
                (string) $data['from'],
                $data['to'] ?? null,
                $data['note'] ?? null,
            ),
            'END_COMPONENT' => $this->compensation->endComponent(
                EmployeeComponent::query()->findOrFail($data['employee_component_id']),
                (string) $data['on'],
            ),
            'CHANGE_COMPONENT' => $this->compensation->changeComponent(
                EmployeeComponent::query()->with('payComponent')->findOrFail($data['employee_component_id']),
                (string) $data['from'],
                (string) $data['amount'],
                $data['note'] ?? null,
            ),
            default => throw new \InvalidArgumentException("Unknown compensation operation [{$operation}]."),
        };
    }
}
