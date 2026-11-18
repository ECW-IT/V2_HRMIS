<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\CoreHr\Database\Factories\RelationshipFactory;

class Relationship extends Model
{
    /** @use HasFactory<RelationshipFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return list<string> */
    public static function activeNames(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    protected static function newFactory(): RelationshipFactory
    {
        return RelationshipFactory::new();
    }
}
