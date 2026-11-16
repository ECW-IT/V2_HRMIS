<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\FundEntryFactory;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\FundEntryKind;

class FundEntry extends Model
{
    /** @use HasFactory<FundEntryFactory> */
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $table = 'fund_ledger';

    protected $fillable = [
        'company_id',
        'employment_id',
        'fund',
        'kind',
        'amount',
        'pay_run_id',
        'pay_period_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fund' => FundCode::class,
            'kind' => FundEntryKind::class,
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Fund ledger entries are append-only.');
        }

        if ($this->created_at === null) {
            $this->created_at = now();
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Fund ledger entries cannot be deleted.');
    }

    protected static function newFactory(): FundEntryFactory
    {
        return FundEntryFactory::new();
    }
}
