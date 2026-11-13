<?php

namespace Modules\CoreHr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Models\Employment;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController
{
    public function __invoke(Request $request, EmployeeDocument $document): StreamedResponse
    {
        abort_unless($request->user()?->can('employee.document.view'), 403);

        $visible = Employment::query()
            ->where('person_id', $document->person_id)
            ->exists();

        abort_unless($visible, 404);

        abort_unless(Storage::disk('private')->exists($document->file_path), 404);

        return Storage::disk('private')->download($document->file_path, $document->original_name);
    }
}
