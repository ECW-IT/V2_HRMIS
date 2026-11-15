<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\EmployeeComponentFactory;

class EmployeeComponent extends Model
{
    /** @use HasFactory<EmployeeComponentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'employee_components';

    /** @var list<string> */
    protected array $auditMasked = ['amount'];

    protected $fillable = [
        'company_id',
        'employment_id',
        'pay_component_id',
        'amount',
        'effective_from',
        'effective_to',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    protected static function newFactory(): EmployeeComponentFactory
    {
        return EmployeeComponentFactory::new();
    }
}
