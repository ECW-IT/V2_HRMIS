<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payroll\Database\Factories\LoanInstalmentFactory;
use Modules\Payroll\Enums\InstalmentStatus;

class LoanInstalment extends Model
{
    /** @use HasFactory<LoanInstalmentFactory> */
    use HasFactory;

    protected $table = 'loan_instalments';

    protected $fillable = [
        'loan_id',
        'sequence',
        'due_month',
        'amount',
        'status',
        'pay_run_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_month' => 'date',
            'amount' => 'decimal:2',
            'status' => InstalmentStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    protected static function newFactory(): LoanInstalmentFactory
    {
        return LoanInstalmentFactory::new();
    }
}
