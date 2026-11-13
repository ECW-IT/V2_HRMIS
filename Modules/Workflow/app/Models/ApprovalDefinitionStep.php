<?php

namespace Modules\Workflow\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workflow\Database\Factories\ApprovalDefinitionStepFactory;
use Modules\Workflow\Enums\ApproverType;

class ApprovalDefinitionStep extends Model
{
    /** @use HasFactory<ApprovalDefinitionStepFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'approval_definition_id',
        'sequence',
        'approver_type',
        'approver_ref',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'approver_type' => ApproverType::class,
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ApprovalDefinition::class, 'approval_definition_id');
    }

    protected static function newFactory(): ApprovalDefinitionStepFactory
    {
        return ApprovalDefinitionStepFactory::new();
    }
}
