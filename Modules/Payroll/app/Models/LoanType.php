<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\LoanTypeFactory;

class LoanType extends Model
{
    /** @use HasFactory<LoanTypeFactory> */
    use HasFactory;

    protected $table = 'loan_types';

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

    protected static function newFactory(): LoanTypeFactory
    {
        return LoanTypeFactory::new();
    }
}
