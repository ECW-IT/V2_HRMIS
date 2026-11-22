<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\DesignationFactory;

class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use Auditable, HasFactory;

    protected $table = 'designations';

    protected $fillable = [
        'code',
        'name',
        'name_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DesignationFactory
    {
        return DesignationFactory::new();
    }
}
