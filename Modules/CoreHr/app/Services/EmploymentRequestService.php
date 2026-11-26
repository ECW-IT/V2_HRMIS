<?php

namespace Modules\CoreHr\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Organisation\Services\StaffWorkCategoryService;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;

/** Submits employment changes for approval. */
final class EmploymentRequestService
{
    public function __construct(
        private readonly EmploymentService $employments,
        private readonly PersonService $persons,
        private readonly BankAccountService $banks,
        private readonly StaffWorkCategoryService $staffCategories,
        private readonly ApprovalService $approvals,
    ) {}

    /** $operation: 'CONFIRM' | 'CHANGE_ASSIGNMENT' | 'UPDATE_PERSON' | 'UPSERT_CONTACT' | 'REMOVE_CONTACT' | 'UPSERT_CHILD' | 'REMOVE_CHILD' | 'UPDATE_CLASSIFICATION' | 'UPDATE_STAFF_CATEGORY' | 'UPDATE_MODULE_FLAGS' | 'SET_BANK' | 'SUSPEND' | 'REINSTATE' | 'EXIT' | 'SISTER_TRANSFER'.
     *  Always returns the pending ApprovalRequest. */
    public function request(User $by, string $operation, Employment $e, array $data): ApprovalRequest
    {
        $permission = match (true) {
            in_array($operation, ['EXIT', 'SISTER_TRANSFER', 'SUSPEND', 'REINSTATE'], true) => 'employee.exit',
            $operation === 'SET_BANK' => 'employee.bank.manage',
            default => 'employee.update',
        };

        if (! $by->can($permission)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($operation === 'SET_BANK') {
            $data['account'] = $this->banks->normalised($e, $data['account'] ?? []);
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
            'UPSERT_CONTACT' => $this->persons->upsertContact($e->person, $data['contact']),
            'REMOVE_CONTACT' => $this->persons->removeContact($e->person, (int) $data['contact_id']),
            'UPSERT_CHILD' => $this->persons->upsertChild($e->person, (string) $data['kind'], $data['child']),
            'REMOVE_CHILD' => $this->persons->removeChild($e->person, (string) $data['kind'], (int) $data['child_id']),
            'UPDATE_CLASSIFICATION' => $this->employments->updateClassification($e, $data['classification']),
            'UPDATE_STAFF_CATEGORY' => $this->staffCategories->assign(
                $e,
                isset($data['staff_work_category_id']) && $data['staff_work_category_id'] !== null && $data['staff_work_category_id'] !== ''
                    ? StaffWorkCategory::query()->findOrFail($data['staff_work_category_id'])
                    : null,
            ),
            'UPDATE_MODULE_FLAGS' => $this->employments->updateModuleFlags($e, $data['flags']),
            'SET_BANK' => $this->banks->set($e, $data['account']),
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

        $recordsEvent = ['CONFIRM', 'CHANGE_ASSIGNMENT', 'SUSPEND', 'REINSTATE', 'EXIT', 'SISTER_TRANSFER'];

        if ($approvalRequestId !== null && in_array($operation, $recordsEvent, true)) {
            $event = $e->events()->orderByDesc('id')->first();
            if ($event !== null && $event->approval_request_id === null) {
                $event->update(['approval_request_id' => $approvalRequestId]);
            }
        }
    }
}
