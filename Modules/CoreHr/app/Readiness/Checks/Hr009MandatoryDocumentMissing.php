<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr009MandatoryDocumentMissing extends AbstractCheck
{
    public function code(): string
    {
        return 'HR009';
    }

    public function title(): string
    {
        return 'Mandatory document missing';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        return $this->ids(
            $this->population($company, $asOf)
                ->whereExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('document_types')
                        ->where('document_types.is_mandatory', true)
                        ->where('document_types.is_active', true)
                        ->whereNotExists(function ($inner) {
                            $inner->selectRaw('1')
                                ->from('employee_documents')
                                ->whereColumn('employee_documents.person_id', 'employments.person_id')
                                ->whereColumn('employee_documents.document_type_id', 'document_types.id')
                                ->whereNull('employee_documents.archived_at');
                        });
                })
        );
    }
}
