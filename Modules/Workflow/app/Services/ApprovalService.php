<?php

namespace Modules\Workflow\Services;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\OrgUnit;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Enums\ApproverType;
use Modules\Workflow\Enums\Decision;
use Modules\Workflow\Exceptions\NotAllowedToAct;
use Modules\Workflow\Exceptions\RequestAlreadyDecided;
use Modules\Workflow\Models\ApprovalDefinition;
use Modules\Workflow\Models\ApprovalDefinitionStep;
use Modules\Workflow\Models\ApprovalRequest;

final class ApprovalService
{
    /** @var array<string, class-string<AppliesApproval>> */
    private static array $handlers = [];

    public static function registerHandler(string $definitionCode, string $handlerClass): void
    {
        self::$handlers[$definitionCode] = $handlerClass;
    }

    public function submit(
        string $definitionCode,
        int $companyId,
        string $subjectType,
        ?int $subjectId,
        ApprovalActionType $action,
        array $payload,
        User $by,
        ?int $employmentId = null,
    ): ApprovalRequest {
        $definition = ApprovalDefinition::query()->where('code', $definitionCode)->first();

        if ($definition === null || ! $definition->is_active) {
            throw new \InvalidArgumentException('Unknown or inactive approval definition.');
        }

        return DB::transaction(function () use ($definition, $companyId, $subjectType, $subjectId, $action, $payload, $by, $employmentId) {
            $steps = $definition->steps()->orderBy('sequence')->get();

            $request = ApprovalRequest::query()->create([
                'approval_definition_id' => $definition->id,
                'company_id' => $companyId,
                'employment_id' => $employmentId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action' => $action,
                'payload' => $payload,
                'status' => ApprovalStatus::PENDING,
                'current_step' => 1,
                'requested_by' => $by->id,
                'requested_at' => now(),
                'decided_at' => null,
            ]);

            if ($steps->isEmpty()) {
                $this->applyHandler($request);
                $request->update([
                    'status' => ApprovalStatus::APPROVED,
                    'decided_at' => now(),
                ]);

                return $request->fresh();
            }

            return $this->advancePastUnresolvableManagerSteps($request->fresh());
        });
    }

    public function approve(ApprovalRequest $request, User $by, ?string $remarks = null): ApprovalRequest
    {
        if ($request->status !== ApprovalStatus::PENDING) {
            throw new RequestAlreadyDecided('This request has already been decided.');
        }

        if (! $this->canAct($request, $by)) {
            throw new NotAllowedToAct('You are not allowed to act on this request.');
        }

        return DB::transaction(function () use ($request, $by, $remarks) {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApprovalStatus::PENDING) {
                throw new RequestAlreadyDecided('This request has already been decided.');
            }

            if (! $this->canAct($request, $by)) {
                throw new NotAllowedToAct('You are not allowed to act on this request.');
            }

            $request->actions()->create([
                'step' => $request->current_step,
                'user_id' => $by->id,
                'decision' => Decision::APPROVED,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            $maxStep = $request->definition->steps()->max('sequence') ?? 0;

            if ($request->current_step < $maxStep) {
                $request->update(['current_step' => $request->current_step + 1]);

                return $this->advancePastUnresolvableManagerSteps($request->fresh());
            }

            $this->applyHandler($request);
            $request->update([
                'status' => ApprovalStatus::APPROVED,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function reject(ApprovalRequest $request, User $by, string $remarks): ApprovalRequest
    {
        if ($request->status !== ApprovalStatus::PENDING) {
            throw new RequestAlreadyDecided('This request has already been decided.');
        }

        if (! $this->canAct($request, $by)) {
            throw new NotAllowedToAct('You are not allowed to act on this request.');
        }

        if (trim($remarks) === '') {
            throw new \InvalidArgumentException('Remarks are required to reject a request.');
        }

        return DB::transaction(function () use ($request, $by, $remarks) {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApprovalStatus::PENDING) {
                throw new RequestAlreadyDecided('This request has already been decided.');
            }

            if (! $this->canAct($request, $by)) {
                throw new NotAllowedToAct('You are not allowed to act on this request.');
            }

            $request->actions()->create([
                'step' => $request->current_step,
                'user_id' => $by->id,
                'decision' => Decision::REJECTED,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            $request->update([
                'status' => ApprovalStatus::REJECTED,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function cancel(ApprovalRequest $request, User $by): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $by) {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApprovalStatus::PENDING || (int) $request->requested_by !== (int) $by->id) {
                throw new NotAllowedToAct('Only the requester can cancel a pending request.');
            }

            $request->update([
                'status' => ApprovalStatus::CANCELLED,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function canAct(ApprovalRequest $request, User $user): bool
    {
        if ($request->status !== ApprovalStatus::PENDING) {
            return false;
        }

        if ((int) $request->requested_by === (int) $user->id) {
            return false;
        }

        if (! in_array((int) $request->company_id, $this->permittedCompanyIds($user), true)) {
            return false;
        }

        if (! $user->can('approval.act')) {
            return false;
        }

        $step = $request->definition->steps()
            ->where('sequence', $request->current_step)
            ->first();

        if ($step === null) {
            return false;
        }

        return $this->userMatchesStep($user, $step, $request);
    }

    /** PENDING requests the user can act on now, within their company scope. */
    public function pendingFor(User $user): Collection
    {
        $companyIds = $this->permittedCompanyIds($user);

        return ApprovalRequest::query()
            ->with(['definition', 'requester', 'company'])
            ->where('status', ApprovalStatus::PENDING)
            ->whereIn('company_id', $companyIds)
            ->orderByDesc('requested_at')
            ->get()
            ->filter(fn (ApprovalRequest $request) => $this->canAct($request, $user))
            ->values();
    }

    private function advancePastUnresolvableManagerSteps(ApprovalRequest $request): ApprovalRequest
    {
        while ($request->status === ApprovalStatus::PENDING) {
            $step = $request->definition->steps()
                ->where('sequence', $request->current_step)
                ->first();

            if ($step === null || $step->approver_type !== ApproverType::MANAGER) {
                break;
            }

            if ($this->resolveManagerApproverUserId($request) !== null) {
                break;
            }

            $request->actions()->create([
                'step' => $request->current_step,
                'user_id' => null,
                'decision' => Decision::APPROVED,
                'remarks' => 'No manager with a user account — step skipped',
                'acted_at' => now(),
            ]);

            $maxStep = $request->definition->steps()->max('sequence') ?? 0;

            if ($request->current_step < $maxStep) {
                $request->update(['current_step' => $request->current_step + 1]);
                $request = $request->fresh();

                continue;
            }

            $this->applyHandler($request);
            $request->update([
                'status' => ApprovalStatus::APPROVED,
                'decided_at' => now(),
            ]);

            break;
        }

        return $request->fresh();
    }

    private function resolveManagerApproverUserId(ApprovalRequest $request): ?int
    {
        if ($request->employment_id === null) {
            return null;
        }

        $employment = Employment::query()->find($request->employment_id);
        if ($employment === null) {
            return null;
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();
        $assignment = $employment->assignmentOn($today);

        $candidates = [];
        if ($assignment?->manager_employment_id) {
            $candidates[] = (int) $assignment->manager_employment_id;
        }

        $orgUnitId = $assignment?->org_unit_id;
        while ($orgUnitId !== null) {
            $unit = OrgUnit::query()->find($orgUnitId);
            if ($unit === null) {
                break;
            }
            if ($unit->head_employment_id) {
                $candidates[] = (int) $unit->head_employment_id;
            }
            $orgUnitId = $unit->parent_id;
        }

        foreach (array_unique($candidates) as $managerEmploymentId) {
            if ($managerEmploymentId === (int) $employment->id) {
                continue;
            }

            $manager = Employment::query()->find($managerEmploymentId);
            if ($manager === null || $manager->status !== EmploymentStatus::ACTIVE) {
                continue;
            }

            $user = User::query()
                ->where('person_id', $manager->person_id)
                ->where('is_active', true)
                ->first();

            if ($user !== null) {
                return (int) $user->id;
            }
        }

        return null;
    }

    private function applyHandler(ApprovalRequest $request): void
    {
        $code = $request->definition->code;
        $handlerClass = self::$handlers[$code] ?? null;

        if ($handlerClass === null) {
            throw new \LogicException("No approval handler registered for [{$code}].");
        }

        /** @var AppliesApproval $handler */
        $handler = app($handlerClass);

        AuditLogger::withReason("Approved request #{$request->id}", function () use ($handler, $request) {
            $handler->apply($request);
        });
    }

    private function userMatchesStep(User $user, ApprovalDefinitionStep $step, ApprovalRequest $request): bool
    {
        return match ($step->approver_type) {
            ApproverType::ROLE => $user->hasRole((string) $step->approver_ref),
            ApproverType::USER => (string) $user->id === (string) $step->approver_ref,
            ApproverType::MANAGER => $this->resolveManagerApproverUserId($request) === (int) $user->id,
        };
    }

    /** @return list<int> */
    private function permittedCompanyIds(User $user): array
    {
        if ($user->all_companies) {
            return Company::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.id')
            ->pluck('companies.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
