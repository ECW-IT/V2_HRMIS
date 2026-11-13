<?php

namespace Modules\CoreHr\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\CoreHr\Exceptions\InvalidDocument;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;

final class DocumentService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /** $meta: document_type_id, employment_id?, issued_on?, expires_on?, remarks? */
    public function upload(Person $p, UploadedFile $file, array $meta, ?User $by): EmployeeDocument
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidDocument('File type is not allowed.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidDocument('File is larger than 10 MB.');
        }

        $type = DocumentType::query()->findOrFail($meta['document_type_id']);
        $expiresOn = $meta['expires_on'] ?? null;
        $issuedOn = $meta['issued_on'] ?? null;

        if ($type->requires_expiry && ($expiresOn === null || $expiresOn === '')) {
            throw new InvalidDocument('Expiry date is required for this document type.');
        }

        if ($expiresOn !== null && $expiresOn !== '' && $issuedOn !== null && $issuedOn !== '' && (string) $expiresOn < (string) $issuedOn) {
            throw new InvalidDocument('Expiry date cannot be before the issued date.');
        }

        $employmentId = $meta['employment_id'] ?? null;
        if ($employmentId !== null) {
            $employment = Employment::query()->find($employmentId);
            if ($employment === null || (int) $employment->person_id !== (int) $p->id) {
                throw new InvalidDocument('Employment does not belong to this person.');
            }
        }

        $filename = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs('documents/'.$p->id, $filename, 'private');

        return EmployeeDocument::query()->create([
            'person_id' => $p->id,
            'employment_id' => $employmentId,
            'document_type_id' => $type->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'issued_on' => $issuedOn ?: null,
            'expires_on' => $expiresOn ?: null,
            'remarks' => $meta['remarks'] ?? null,
            'uploaded_by' => $by?->id,
        ]);
    }

    public function archive(EmployeeDocument $doc): void
    {
        $doc->update(['archived_at' => now()]);
    }
}
