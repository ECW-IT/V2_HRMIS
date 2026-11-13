<?php

namespace Modules\CoreHr\Services;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;

/** Decides between applying directly and submitting for approval. */
final class EmploymentRequestService
{
    public function __construct(
        private readonly EmploymentService $employments,
        private readonly PersonService $persons,
        private readonly ApprovalService $approvals,
    ) {}

    /** $operation: 'CONFIRM' | 'CHANGE_ASSIGNMENT' | 'UPDATE_PERSON' | 'SUSPEND' | 'REINSTATE' | 'EXIT' | 'SISTER_TRANSFER'.
     *  Returns the ApprovalRequest when submitted, or null when applied directly. */
    public function request(User $by, string $operation, Employment $e, array $data): ?ApprovalRequest
    {
        $permission = in_array($operation, ['EXIT', 'SISTER_TRANSFER', 'SUSPEND', 'REINSTATE'], true)
            ? 'employee.exit'
            : 'employee.update';

        if (! $by->can($permission)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($by->can('employee.apply-directly')) {
            AuditLogger::withReason($data['reason'] ?? $operation, function () use ($operation, $e, $data) {
                $this->apply($operation, $e, $data);
            });

            return null;
        }

        $definition = in_array($operation, ['EXIT', 'SISTER_TRANSFER'], true)
            ? 'EMPLOYEE_EXIT'
            : 'EMPLOYEE_CHANGE';

        return $this->approvals->submit(
            $definition,
            (int) $e->company_id,
            Employment::class,
            $e->id,
            ApprovalActionType::UPDATE,
            array_merge(['operation' => $operation], $data),
            $by,
        );
    }

    /** @param  array<string, mixed>  $data */
    public function apply(string $operation, Employment $e, array $data, ?int $approvalRequestId = null): void
    {
        match ($operation) {
            'CONFIRM' => $this->employments->confirm($e, $data['on']),
            'CHANGE_ASSIGNMENT' => $this->employments->changeAssignment(
                $e,
                $data['from'],
                $data['changes'],
                $data['reason'],
            ),
            'UPDATE_PERSON' => $this->persons->update($e->person, $data['person']),
            'SUSPEND' => $this->employments->suspend($e, $data['on'], $data['remarks']),
            'REINSTATE' => $this->employments->reinstate($e, $data['on']),
            'EXIT' => $this->employments->exit(
                $e,
                $data['on'],
                $data['reason'] instanceof ExitReason
                    ? $data['reason']
                    : ExitReason::from((string) $data['reason']),
                $data['remarks'] ?? null,
            ),
            'SISTER_TRANSFER' => $this->employments->transferToSisterCompany(
                $e,
                Company::query()->findOrFail($data['to_company_id']),
                $data['last_day'],
                $data['assignment'],
                $data['new_employment'] ?? [],
            ),
            default => throw new \InvalidArgumentException("Unknown employment operation [{$operation}]."),
        };

        if ($approvalRequestId !== null && $operation !== 'UPDATE_PERSON') {
            $event = $e->events()->orderByDesc('id')->first();
            if ($event !== null && $event->approval_request_id === null) {
                $event->update(['approval_request_id' => $approvalRequestId]);
            }
        }
    }
}
