<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Models\Person;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'employment_id' => null,
            'document_type_id' => DocumentType::factory(),
            'file_path' => 'documents/1/'.fake()->uuid().'.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'issued_on' => null,
            'expires_on' => null,
            'remarks' => null,
            'uploaded_by' => null,
            'archived_at' => null,
        ];
    }
}
