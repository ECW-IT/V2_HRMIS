<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\GradeFactory;

class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'rank',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): GradeFactory
    {
        return GradeFactory::new();
    }
}
