<?php

namespace Modules\Workflow\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Workflow\Database\Factories\ApprovalRequestFactory;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;

class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'approval_definition_id',
        'company_id',
        'employment_id',
        'subject_type',
        'subject_id',
        'action',
        'payload',
        'status',
        'current_step',
        'requested_by',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalActionType::class,
            'payload' => 'array',
            'status' => ApprovalStatus::class,
            'current_step' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ApprovalDefinition::class, 'approval_definition_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    protected static function newFactory(): ApprovalRequestFactory
    {
        return ApprovalRequestFactory::new();
    }
}
