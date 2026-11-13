<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Database\Factories\DocumentTypeFactory;

class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'requires_expiry',
        'is_mandatory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_expiry' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }
}
