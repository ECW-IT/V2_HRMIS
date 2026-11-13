<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\EmployeeBankAccountFactory;

class EmployeeBankAccount extends Model
{
    /** @use HasFactory<EmployeeBankAccountFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'employment_id';

    /** @var list<string> */
    protected array $auditMasked = ['account_no'];

    protected $fillable = [
        'employment_id',
        'effective_from',
        'effective_to',
        'is_cash',
        'bank_id',
        'bank_branch_id',
        'account_no',
        'account_name',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_cash' => 'boolean',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    protected static function newFactory(): EmployeeBankAccountFactory
    {
        return EmployeeBankAccountFactory::new();
    }
}
