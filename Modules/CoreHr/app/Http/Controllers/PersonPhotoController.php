<?php

namespace Modules\CoreHr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PersonPhotoController
{
    public function __invoke(Request $request, Person $person): StreamedResponse
    {
        abort_unless($request->user()?->can('employee.view'), 403);

        $visible = Employment::query()
            ->where('person_id', $person->id)
            ->exists();

        abort_unless($visible, 404);

        $path = $person->photo_path;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('private')->exists($path), 404);

        $mime = Storage::disk('private')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('private')->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
