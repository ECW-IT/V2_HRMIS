<?php

namespace Modules\CoreHr\Http\Controllers;

use Illuminate\Http\Request;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\PersonalSheetPdfService;
use Symfony\Component\HttpFoundation\Response;

final class PersonalSheetPdfController
{
    public function __invoke(Request $request, Employment $employment, PersonalSheetPdfService $sheets): Response
    {
        abort_unless($request->user()?->can('employee.view'), 403);

        $visible = Employment::query()->whereKey($employment->id)->exists();
        abort_unless($visible, 404);

        $filename = 'personal-sheet-'.$employment->employee_no.'.pdf';

        return $sheets->pdf($employment)->download($filename);
    }
}
