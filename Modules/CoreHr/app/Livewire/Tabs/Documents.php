<?php

namespace Modules\CoreHr\Livewire\Tabs;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\DocumentService;

class Documents extends Component
{
    use WithFileUploads;

    public Employment $employment;

    public bool $showUpload = false;

    public $file = null;

    public string|int|null $documentTypeId = null;

    public string $issuedOn = '';

    public string $expiresOn = '';

    public string $remarks = '';

    public string $message = '';

    public string $errorMessage = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
    }

    public function openUpload(): void
    {
        $this->authorize('employee.document.manage');

        $this->reset(['file', 'documentTypeId', 'issuedOn', 'expiresOn', 'remarks', 'message', 'errorMessage']);
        $this->resetErrorBag();
        $this->showUpload = true;
    }

    public function closeUpload(): void
    {
        $this->showUpload = false;
    }

    public function upload(DocumentService $documents): void
    {
        $this->authorize('employee.document.manage');

        $this->message = '';
        $this->errorMessage = '';

        $this->validate([
            'file' => ['required', 'file'],
            'documentTypeId' => ['required', 'integer', 'exists:document_types,id'],
            'issuedOn' => ['nullable', 'date_format:Y-m-d'],
            'expiresOn' => ['nullable', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $documents->upload($this->employment->person, $this->file, [
                'document_type_id' => (int) $this->documentTypeId,
                'employment_id' => $this->employment->id,
                'issued_on' => $this->issuedOn !== '' ? $this->issuedOn : null,
                'expires_on' => $this->expiresOn !== '' ? $this->expiresOn : null,
                'remarks' => $this->remarks !== '' ? $this->remarks : null,
            ], auth()->user());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['file', 'documentTypeId', 'issuedOn', 'expiresOn', 'remarks']);
        $this->showUpload = false;
        $this->message = 'Document uploaded.';
    }

    public function archive(int $documentId, DocumentService $documents): void
    {
        $this->authorize('employee.document.manage');

        $document = EmployeeDocument::query()
            ->where('person_id', $this->employment->person_id)
            ->whereNull('archived_at')
            ->findOrFail($documentId);

        $documents->archive($document);
        $this->message = 'Document archived.';
    }

    public function render(): View
    {
        $this->authorize('employee.document.view');

        $documents = EmployeeDocument::query()
            ->with(['documentType', 'uploader'])
            ->where('person_id', $this->employment->person_id)
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->get();

        $missing = DocumentType::query()
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->whereNotIn('id', $documents->pluck('document_type_id')->all())
            ->orderBy('name')
            ->get();

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return view('corehr::livewire.tabs.documents', [
            'documents' => $documents,
            'missing' => $missing,
            'types' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'requires_expiry']),
            'today' => $today,
            'soon' => $today->addDays(30),
        ]);
    }
}
