<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\AttendanceAdjustmentFactory;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\AdjustmentType;
use Modules\CoreHr\Models\Employment;
use Modules\Workflow\Models\ApprovalRequest;

class AttendanceAdjustment extends Model
{
    /** @use HasFactory<AttendanceAdjustmentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'employment_id',
        'work_date',
        'type',
        'punched_at',
        'punch_id',
        'reason',
        'status',
        'approval_request_id',
        'requested_by',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'punched_at' => 'datetime',
            'type' => AdjustmentType::class,
            'status' => AdjustmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function punch(): BelongsTo
    {
        return $this->belongsTo(Punch::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected static function newFactory(): AttendanceAdjustmentFactory
    {
        return AttendanceAdjustmentFactory::new();
    }
}
