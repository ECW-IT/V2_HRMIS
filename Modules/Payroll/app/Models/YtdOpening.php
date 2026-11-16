<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\YtdOpeningFactory;

class YtdOpening extends Model
{
    /** @use HasFactory<YtdOpeningFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'ytd_openings';

    protected $fillable = [
        'company_id',
        'employment_id',
        'tax_year_start',
        'months_covered',
        'gross',
        'taxable_pay',
        'tax',
        'epf_employee',
        'epf_employer',
        'etf',
        'note',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_year_start' => 'date',
            'months_covered' => 'integer',
            'gross' => 'decimal:2',
            'taxable_pay' => 'decimal:2',
            'tax' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'etf' => 'decimal:2',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    protected static function newFactory(): YtdOpeningFactory
    {
        return YtdOpeningFactory::new();
    }
}
