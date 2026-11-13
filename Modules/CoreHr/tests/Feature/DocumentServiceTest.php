<?php

namespace Modules\CoreHr\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\CoreHr\Exceptions\InvalidDocument;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\DocumentService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private DocumentService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->svc = app(DocumentService::class);
    }

    private function type(string $code, bool $expiry = false): DocumentType
    {
        return DocumentType::factory()->create([
            'code' => $code,
            'requires_expiry' => $expiry,
        ]);
    }

    private function uploadFor(Person $p): EmployeeDocument
    {
        return $this->svc->upload(
            $p,
            UploadedFile::fake()->create('nic.pdf', 10, 'application/pdf'),
            ['document_type_id' => $this->type('OTHER')->id],
            null,
        );
    }

    public function test_upload_stores_on_the_private_disk(): void
    {
        $p = $this->person();
        $doc = $this->svc->upload($p, UploadedFile::fake()->create('nic.pdf', 200, 'application/pdf'), ['document_type_id' => $this->type('NIC_COPY')->id], null);
        Storage::disk('private')->assertExists($doc->file_path);
        $this->assertStringStartsWith("documents/{$p->id}/", $doc->file_path);
        $this->assertSame('nic.pdf', $doc->original_name);
    }

    public function test_disallowed_file_type_is_refused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->svc->upload($this->person(), UploadedFile::fake()->create('run.exe', 10), ['document_type_id' => $this->type('OTHER')->id], null);
    }

    public function test_file_over_ten_megabytes_is_refused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->svc->upload($this->person(), UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), ['document_type_id' => $this->type('OTHER')->id], null);
    }

    public function test_expiring_type_requires_expiry_date(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->svc->upload($this->person(), UploadedFile::fake()->create('lic.pdf', 10, 'application/pdf'), ['document_type_id' => $this->type('DRIVING_LICENCE', expiry: true)->id], null);
    }

    public function test_download_needs_permission(): void
    {
        $e = $this->hired();
        $doc = $this->uploadFor($e->person);
        $this->actingAs(User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]))
            ->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_download_is_refused_for_a_person_in_another_company(): void
    {
        $e = $this->hired();
        $doc = $this->uploadFor($e->person);
        $this->actingAs(User::factory()->forCompanies([$this->company()])->withPermissions('employee.document.view')->create(['must_change_password' => false]))
            ->get(route('documents.download', $doc))->assertNotFound();
    }

    public function test_download_works_with_permission_and_company_access(): void
    {
        $e = $this->hired();
        $doc = $this->uploadFor($e->person);
        $this->actingAs(User::factory()->forCompanies([$e->company])->withPermissions('employee.document.view')->create(['must_change_password' => false]))
            ->get(route('documents.download', $doc))->assertOk()->assertDownload($doc->original_name);
    }

    public function test_archive_keeps_the_file(): void
    {
        $doc = $this->uploadFor($this->person());
        $this->svc->archive($doc);
        $this->assertNotNull($doc->fresh()->archived_at);
        Storage::disk('private')->assertExists($doc->file_path);
    }
}
