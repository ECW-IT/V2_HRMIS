<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\BiometricEnrolmentFactory;
use Modules\CoreHr\Models\Employment;

class BiometricEnrolment extends Model
{
    /** @use HasFactory<BiometricEnrolmentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employment_id',
        'device_group',
        'device_user_id',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'device_user_id' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    protected static function newFactory(): BiometricEnrolmentFactory
    {
        return BiometricEnrolmentFactory::new();
    }
}
