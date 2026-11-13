<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\DocumentType;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('DOC_????'));

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'requires_expiry' => false,
            'is_mandatory' => false,
            'is_active' => true,
        ];
    }
}
