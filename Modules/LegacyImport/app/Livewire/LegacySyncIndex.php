<?php

namespace Modules\LegacyImport\Livewire;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Modules\LegacyImport\Models\LegacySyncRun;
use Modules\LegacyImport\Services\LegacySyncExporter;
use Modules\Organisation\Models\Company;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacySyncIndex extends Component
{
    public string $message = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function runNow(LegacySyncExporter $exporter): void
    {
        $this->authorizeAccess();

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->message = 'Select a company first.';

            return;
        }

        $company = Company::query()->findOrFail($companyId);
        $run = $exporter->run($company);
        $this->message = $run->file_path === ''
            ? 'Run recorded with nothing to report.'
            : "Export ready: {$run->new_hires} hires, {$run->exits} exits, {$run->changes} changes.";
    }

    public function markApplied(int $runId): void
    {
        $this->authorizeAccess();

        $run = LegacySyncRun::query()->findOrFail($runId);
        if ($run->applied_at !== null) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $run->update([
            'applied_by' => $user->id,
            'applied_at' => now(config('app.timezone')),
        ]);
        $this->message = "Run #{$run->id} marked as applied.";
    }

    public function download(int $runId): StreamedResponse
    {
        $this->authorizeAccess();

        $run = LegacySyncRun::query()->findOrFail($runId);
        abort_if($run->file_path === '' || ! Storage::disk('private')->exists($run->file_path), 404);

        return Storage::disk('private')->download(
            $run->file_path,
            basename($run->file_path),
        );
    }

    public function render(): View
    {
        $this->authorizeAccess();

        $runs = LegacySyncRun::query()
            ->with(['appliedByUser', 'company'])
            ->orderByDesc('id')
            ->get();

        return view('legacy_import::livewire.legacy-sync-index', [
            'runs' => $runs,
        ])->layout('components.layouts.app', ['title' => 'Legacy sync']);
    }

    private function authorizeAccess(): void
    {
        $this->authorize('employee.view');
        $this->authorize('employee.bank.view');
    }
}
