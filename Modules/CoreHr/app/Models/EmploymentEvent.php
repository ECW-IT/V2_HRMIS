<?php

namespace Modules\CoreHr\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\EmploymentEventFactory;
use Modules\CoreHr\Enums\EventType;
use Modules\Workflow\Models\ApprovalRequest;

class EmploymentEvent extends Model
{
    /** @use HasFactory<EmploymentEventFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employment_id',
        'type',
        'event_date',
        'details',
        'related_employment_id',
        'approval_request_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'event_date' => 'date',
            'details' => 'array',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function relatedEmployment(): BelongsTo
    {
        return $this->belongsTo(Employment::class, 'related_employment_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): EmploymentEventFactory
    {
        return EmploymentEventFactory::new();
    }
}
