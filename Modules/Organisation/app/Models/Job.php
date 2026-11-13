<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\JobFactory;

class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use Auditable, HasFactory;

    protected $table = 'hr_jobs';

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

    protected static function newFactory(): JobFactory
    {
        return JobFactory::new();
    }
}
