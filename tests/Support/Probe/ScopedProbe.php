<?php

namespace Tests\Support\Probe;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Probe\Factories\ScopedProbeFactory;

class ScopedProbe extends Model
{
    /** @use HasFactory<ScopedProbeFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'probe_scoped';

    /** @var list<string> */
    protected array $auditMasked = ['account_no'];

    protected $fillable = [
        'company_id',
        'name',
        'account_no',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ScopedProbeFactory
    {
        return ScopedProbeFactory::new();
    }
}
