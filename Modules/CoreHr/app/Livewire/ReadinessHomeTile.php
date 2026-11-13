<?php

namespace Modules\CoreHr\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

class ReadinessHomeTile extends Component
{
    public function render(ReadinessService $readiness): View
    {
        $asOf = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $selected = CompanyContext::id();
        $companyIds = $selected !== null ? [$selected] : CompanyContext::permittedIds();
        $companies = Company::query()->whereIn('id', $companyIds)->orderBy('id')->get();

        $blockers = 0;
        $warnings = 0;

        foreach ($companies as $company) {
            foreach ($readiness->summary($company, $asOf) as $row) {
                $severity = $row['severity'] instanceof Severity
                    ? $row['severity']
                    : Severity::from((string) $row['severity']);
                if ($severity === Severity::BLOCKER) {
                    $blockers += $row['count'];
                } else {
                    $warnings += $row['count'];
                }
            }
        }

        return view('corehr::livewire.readiness-home-tile', [
            'blockers' => $blockers,
            'warnings' => $warnings,
        ]);
    }
}
