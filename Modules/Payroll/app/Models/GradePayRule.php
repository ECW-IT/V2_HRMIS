<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Database\Factories\GradePayRuleFactory;
use Modules\Payroll\Enums\ExtraDayMethod;

class GradePayRule extends Model
{
    /** @use HasFactory<GradePayRuleFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'grade_id';

    protected $fillable = [
        'grade_id',
        'extra_day_method',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'extra_day_method' => ExtraDayMethod::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    protected static function newFactory(): GradePayRuleFactory
    {
        return GradePayRuleFactory::new();
    }
}
