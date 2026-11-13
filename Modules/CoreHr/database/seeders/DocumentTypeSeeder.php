<?php

namespace Modules\CoreHr\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CoreHr\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = require database_path('seeders/data/document_types.php');

        foreach ($rows as $row) {
            DocumentType::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'requires_expiry' => $row['requires_expiry'],
                    'is_mandatory' => $row['is_mandatory'],
                    'is_active' => true,
                ],
            );
        }
    }
}
