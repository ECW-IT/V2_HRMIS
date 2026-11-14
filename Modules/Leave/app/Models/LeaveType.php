<?php

namespace Modules\Leave\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Leave\Database\Factories\LeaveTypeFactory;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'counts_as_credited',
        'allows_half_day',
        'enforce_balance',
        'requires_document',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_credited' => 'boolean',
            'allows_half_day' => 'boolean',
            'enforce_balance' => 'boolean',
            'requires_document' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    protected static function newFactory(): LeaveTypeFactory
    {
        return LeaveTypeFactory::new();
    }
}
