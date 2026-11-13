<?php

namespace Tests\Support\Probe;

use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Model;

class EffectiveProbe extends Model
{
    use EffectiveDated;

    protected $table = 'probe_effective';

    protected string $effectiveOwnerKey = 'owner_id';

    protected $fillable = [
        'owner_id',
        'effective_from',
        'effective_to',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
