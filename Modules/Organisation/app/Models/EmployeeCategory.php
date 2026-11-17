<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\EmployeeCategoryFactory;

class EmployeeCategory extends Model
{
    /** @use HasFactory<EmployeeCategoryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_key',
        'level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): EmployeeCategoryFactory
    {
        return EmployeeCategoryFactory::new();
    }
}
