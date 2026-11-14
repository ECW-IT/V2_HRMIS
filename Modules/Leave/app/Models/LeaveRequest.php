<?php

namespace Modules\Leave\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Database\Factories\LeaveRequestFactory;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Workflow\Models\ApprovalRequest;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'employment_id',
        'leave_type_id',
        'date',
        'portion',
        'days',
        'reason',
        'covering_employment_id',
        'status',
        'approval_request_id',
        'requested_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'portion' => LeavePortion::class,
            'days' => 'decimal:1',
            'status' => LeaveStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function coveringEmployment(): BelongsTo
    {
        return $this->belongsTo(Employment::class, 'covering_employment_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    protected static function newFactory(): LeaveRequestFactory
    {
        return LeaveRequestFactory::new();
    }
}
