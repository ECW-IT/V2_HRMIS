<?php

namespace Modules\CoreHr\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PersonalSheetPdfTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_personal_sheet_needs_employee_view(): void
    {
        $e = $this->hired();
        $user = User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('employees.personal-sheet', $e))
            ->assertForbidden();
    }

    public function test_personal_sheet_of_another_company_returns_404(): void
    {
        $mine = $this->company();
        $theirs = $this->hired(c: $this->company());
        $this->loginAs('hr-officer', $mine);

        $this->get(route('employees.personal-sheet', $theirs))->assertNotFound();
    }

    public function test_personal_sheet_downloads_pdf_for_permitted_user(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        $response = $this->get(route('employees.personal-sheet', $e));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=personal-sheet-'.$e->employee_no.'.pdf'
        );
    }

    public function test_profile_shows_download_personal_sheet_link(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        $this->get(route('employees.show', $e))
            ->assertOk()
            ->assertSee('Download personal sheet')
            ->assertSee(route('employees.personal-sheet', $e, absolute: false), false);
    }
}
