<?php

namespace Modules\Workflow\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workflow\Database\Factories\ApprovalActionFactory;
use Modules\Workflow\Enums\Decision;

class ApprovalAction extends Model
{
    /** @use HasFactory<ApprovalActionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'approval_request_id',
        'step',
        'user_id',
        'decision',
        'remarks',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'decision' => Decision::class,
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): ApprovalActionFactory
    {
        return ApprovalActionFactory::new();
    }
}
