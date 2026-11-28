<?php

namespace Modules\Payroll\Models;

use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Database\Factories\PayRunEmployeeFactory;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;

class PayRunEmployee extends Model
{
    /** @use HasFactory<PayRunEmployeeFactory> */
    use BelongsToCompany, HasFactory;

    protected $table = 'pay_run_employees';

    protected $fillable = [
        'pay_run_id',
        'company_id',
        'employment_id',
        'status',
        'error',
        'employee_no',
        'name',
        'employee_category_id',
        'org_unit_id',
        'designation_id',
        'employment_type',
        'epf_liable',
        'epf_no',
        'basic_salary',
        'ot_rate',
        'extra_day_method',
        'attendance_tier_code',
        'expected_days',
        'credited_days',
        'extra_days',
        'nopay_days',
        'late_minutes_chargeable',
        'ot_minutes',
        'carried_forward_in',
        'is_cash',
        'bank_id',
        'bank_branch_id',
        'account_no',
        'account_name',
        'gross_sheet1',
        'gross_sheet2',
        'total_deductions',
        'net_pay',
        'carried_forward_out',
        'epf_base',
        'taxable_pay',
        'employer_total',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayRunEmployeeStatus::class,
            'epf_liable' => 'boolean',
            'basic_salary' => 'decimal:2',
            'ot_rate' => 'decimal:2',
            'extra_day_method' => ExtraDayMethod::class,
            'expected_days' => 'decimal:1',
            'credited_days' => 'decimal:2',
            'extra_days' => 'decimal:2',
            'nopay_days' => 'decimal:2',
            'late_minutes_chargeable' => 'integer',
            'ot_minutes' => 'integer',
            'carried_forward_in' => 'decimal:2',
            'is_cash' => 'boolean',
            'gross_sheet1' => 'decimal:2',
            'gross_sheet2' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'carried_forward_out' => 'decimal:2',
            'epf_base' => 'decimal:2',
            'taxable_pay' => 'decimal:2',
            'employer_total' => 'decimal:2',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function employeeCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayRunLine::class);
    }

    public function save(array $options = []): bool
    {
        $this->assertRunNotLocked();

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        $this->assertRunNotLocked();

        return parent::delete();
    }

    private function assertRunNotLocked(): void
    {
        $run = $this->relationLoaded('payRun') ? $this->payRun : PayRun::query()->find($this->pay_run_id);
        if ($run !== null && $run->status === PayRunStatus::LOCKED) {
            throw new \LogicException('A locked pay run cannot be changed.');
        }
    }

    protected static function newFactory(): PayRunEmployeeFactory
    {
        return PayRunEmployeeFactory::new();
    }
}
