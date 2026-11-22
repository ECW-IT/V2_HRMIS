<?php

namespace Modules\CoreHr\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\CoreHr\Enums\AddressType;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\PersonAddress;
use Modules\CoreHr\Models\PersonContact;
use Modules\CoreHr\Support\AssignmentLookup;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Payroll\Services\CompensationService;

final class PersonalSheetPdfService
{
    public function __construct(
        private readonly CompensationService $compensation,
        private readonly BankAccountService $banks,
    ) {}

    public function pdf(Employment $employment): DomPdf
    {
        return Pdf::loadView('corehr::pdf.personal-sheet', $this->data($employment))
            ->setPaper('a4');
    }

    /** @return array<string, mixed> */
    public function data(Employment $employment): array
    {
        $today = CarbonImmutable::now(config('app.timezone'));
        $person = $employment->person()->with([
            'contacts',
            'addresses',
            'emergencyContacts',
            'documents' => fn ($q) => $q->whereNull('archived_at'),
        ])->firstOrFail();

        $employment->loadMissing(['employeeCategory', 'staffWorkCategory', 'company']);

        $assignment = AssignmentLookup::display($employment);
        $assignment?->loadMissing(['orgUnit.parent.parent', 'designation']);

        $permanent = $person->addresses->first(
            fn (PersonAddress $a) => $a->type === AddressType::PERMANENT
        );
        $current = $person->addresses->first(
            fn (PersonAddress $a) => $a->type === AddressType::CURRENT
        );

        $comp = $this->compensation->on($employment, $today->toDateString());
        $canViewBank = Auth::user()?->can('employee.bank.view') ?? false;
        $bank = $canViewBank ? $this->banks->on($employment, $today->toDateString()) : null;

        $heldTypeIds = $person->documents->pluck('document_type_id')->unique()->all();
        $documentTypes = DocumentType::query()->orderBy('name')->get(['id', 'code', 'name']);

        return [
            'generatedAt' => $today->format('Y-m-d H:i'),
            'logoSrc' => $this->dataUri(public_path('images/edirisingha-logo.png')),
            'photoSrc' => $this->photoDataUri($person->photo_path),
            'nameWithInitials' => $person->name_with_initials,
            'fullName' => trim((string) ($person->full_name ?: implode(' ', array_filter([
                $person->first_name,
                $person->middle_name,
                $person->last_name,
            ])))),
            'callingName' => $person->calling_name ?: '—',
            'employeeNo' => $employment->employee_no,
            'epfNo' => $employment->epf_liable ? ($employment->epf_no ?: '—') : 'Not liable',
            'gender' => $person->gender?->value
                ? ucfirst(strtolower($person->gender->value))
                : '—',
            'dateOfBirth' => $person->date_of_birth?->format('Y-m-d') ?? '—',
            'hireDate' => $employment->hire_date->format('Y-m-d'),
            'region' => $person->region ?: '—',
            'maritalStatus' => $person->marital_status?->value
                ? ucfirst(strtolower($person->marital_status->value))
                : '—',
            'nic' => $person->nic ?: '—',
            'passportNo' => $person->passport_no ?: '—',
            'permanentAddress' => $this->formatAddress($permanent),
            'temporaryAddress' => $this->formatAddress($current),
            'mobileNo' => $this->contactValue($person->contacts, ContactType::MOBILE),
            'homeNo' => $this->contactValue($person->contacts, ContactType::HOME),
            'email' => $this->contactValue($person->contacts, ContactType::EMAIL),
            'landlineNo' => $person->landline_no ?: '—',
            'district' => $person->electorate_district ?: '—',
            'gnDivision' => $person->gn_division ?: '—',
            'department' => ($orgUnits = $this->orgUnitsByType($assignment?->orgUnit))[OrgUnitType::DEPARTMENT->value] ?? '—',
            'section' => $orgUnits[OrgUnitType::SECTION->value] ?? '—',
            'subsection' => $orgUnits[OrgUnitType::SUBSECTION->value] ?? '—',
            'designation' => $assignment?->designation?->name ?? '—',
            'employeeCategory' => $employment->employeeCategory?->name ?? '—',
            'staffWorkCategory' => $employment->staffWorkCategory?->name ?? '—',
            'emergencyContacts' => $person->emergencyContacts->map(fn ($c) => [
                'name' => $c->name,
                'relationship' => $c->relationship,
                'address' => $c->address ?: '—',
                'phone' => $c->phone,
            ])->all(),
            'basicSalary' => $comp !== null
                ? number_format((float) $comp->basic_salary, 2, '.', ',')
                : '—',
            'bankAccount' => $this->formatBank($bank, $canViewBank),
            'documents' => $documentTypes->map(fn (DocumentType $type) => [
                'name' => $type->name,
                'held' => in_array($type->id, $heldTypeIds, true),
            ])->all(),
        ];
    }

    private function formatAddress(?PersonAddress $address): string
    {
        if ($address === null) {
            return '—';
        }

        $parts = array_filter([
            $address->line1,
            $address->line2,
            $address->city,
            $address->district,
            $address->postal_code,
        ], fn ($v) => $v !== null && $v !== '');

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /** @param  Collection<int, PersonContact>  $contacts */
    private function contactValue($contacts, ContactType $type): string
    {
        $primary = $contacts->first(fn (PersonContact $c) => $c->type === $type && $c->is_primary)
            ?? $contacts->first(fn (PersonContact $c) => $c->type === $type);

        return $primary?->value ?: '—';
    }

    /** @return array<string, string> */
    private function orgUnitsByType(mixed $orgUnit): array
    {
        $map = [];
        $unit = $orgUnit;
        while ($unit !== null) {
            $type = $unit->type instanceof OrgUnitType ? $unit->type->value : (string) $unit->type;
            $map[$type] = $unit->name;
            $unit = $unit->parent;
        }

        return $map;
    }

    private function formatBank(mixed $bank, bool $canView): string
    {
        if (! $canView) {
            return '—';
        }

        if ($bank === null) {
            return '—';
        }

        if ($bank->is_cash) {
            return 'Cash';
        }

        return (string) ($bank->account_no ?: '—');
    }

    private function photoDataUri(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Storage::disk('private')->exists($path)) {
            return null;
        }

        $binary = Storage::disk('private')->get($path);
        $mime = Storage::disk('private')->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function dataUri(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }
}
