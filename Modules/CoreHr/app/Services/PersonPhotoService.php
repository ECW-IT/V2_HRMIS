<?php

namespace Modules\CoreHr\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CoreHr\Exceptions\InvalidPersonPhoto;
use Modules\CoreHr\Models\Person;

final class PersonPhotoService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    private const MAX_BYTES = 2 * 1024 * 1024;

    public function store(Person $person, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidPersonPhoto('Photo must be a JPG or PNG file.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidPersonPhoto('Photo is larger than 2 MB.');
        }

        $this->deleteFileIfPresent($person);

        $filename = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs('photos/'.$person->id, $filename, 'private');

        $person->update(['photo_path' => $path]);

        return $path;
    }

    public function delete(Person $person): void
    {
        $this->deleteFileIfPresent($person);
        $person->update(['photo_path' => null]);
    }

    private function deleteFileIfPresent(Person $person): void
    {
        $path = $person->photo_path;
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }
}
