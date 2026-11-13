<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\CompanyFactory;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'employee_no_prefix',
        'employee_no_padding',
        'next_employee_seq',
        'epf_employer_no',
        'etf_employer_no',
        'tin',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'employee_no_padding' => 'integer',
            'next_employee_seq' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
