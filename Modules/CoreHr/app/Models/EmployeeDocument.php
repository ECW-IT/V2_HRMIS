<?php

namespace Modules\CoreHr\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\EmployeeDocumentFactory;

class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'employment_id',
        'document_type_id',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'issued_on',
        'expires_on',
        'remarks',
        'uploaded_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'archived_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected static function newFactory(): EmployeeDocumentFactory
    {
        return EmployeeDocumentFactory::new();
    }
}
