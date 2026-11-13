<?php

namespace Modules\Workflow\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Workflow\Database\Factories\ApprovalDefinitionFactory;

class ApprovalDefinition extends Model
{
    /** @use HasFactory<ApprovalDefinitionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalDefinitionStep::class)->orderBy('sequence');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    protected static function newFactory(): ApprovalDefinitionFactory
    {
        return ApprovalDefinitionFactory::new();
    }
}
