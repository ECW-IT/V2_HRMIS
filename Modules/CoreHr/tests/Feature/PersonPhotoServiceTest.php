<?php

namespace Modules\CoreHr\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\CoreHr\Exceptions\InvalidPersonPhoto;
use Modules\CoreHr\Livewire\Tabs\Personal;
use Modules\CoreHr\Services\PersonPhotoService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PersonPhotoServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private PersonPhotoService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        $this->svc = app(PersonPhotoService::class);
    }

    public function test_store_puts_photo_on_the_private_disk_only(): void
    {
        $p = $this->person();
        $path = $this->svc->store($p, UploadedFile::fake()->image('face.jpg', 120, 120));

        Storage::disk('private')->assertExists($path);
        $this->assertStringStartsWith("photos/{$p->id}/", $path);
        $this->assertSame($path, $p->fresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_store_replaces_previous_photo(): void
    {
        $p = $this->person();
        $first = $this->svc->store($p, UploadedFile::fake()->image('one.jpg'));
        $second = $this->svc->store($p->fresh(), UploadedFile::fake()->image('two.png'));

        Storage::disk('private')->assertMissing($first);
        Storage::disk('private')->assertExists($second);
        $this->assertSame($second, $p->fresh()->photo_path);
    }

    public function test_disallowed_file_type_is_refused(): void
    {
        $this->expectException(InvalidPersonPhoto::class);
        $this->svc->store($this->person(), UploadedFile::fake()->create('face.gif', 20, 'image/gif'));
    }

    public function test_file_over_two_megabytes_is_refused(): void
    {
        $this->expectException(InvalidPersonPhoto::class);
        $this->svc->store($this->person(), UploadedFile::fake()->create('face.jpg', 2049, 'image/jpeg'));
    }

    public function test_delete_clears_photo_path_and_file(): void
    {
        $p = $this->person();
        $path = $this->svc->store($p, UploadedFile::fake()->image('face.jpg'));
        $this->svc->delete($p->fresh());

        Storage::disk('private')->assertMissing($path);
        $this->assertNull($p->fresh()->photo_path);
    }

    public function test_photo_route_needs_permission(): void
    {
        $e = $this->hired();
        $this->svc->store($e->person, UploadedFile::fake()->image('face.jpg'));

        $this->actingAs(User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]))
            ->get(route('persons.photo', $e->person))
            ->assertForbidden();
    }

    public function test_photo_route_is_refused_for_a_person_in_another_company(): void
    {
        $e = $this->hired();
        $this->svc->store($e->person, UploadedFile::fake()->image('face.jpg'));

        $this->actingAs(User::factory()->forCompanies([$this->company()])->withPermissions('employee.view')->create(['must_change_password' => false]))
            ->get(route('persons.photo', $e->person))
            ->assertNotFound();
    }

    public function test_photo_route_streams_with_permission_and_company_access(): void
    {
        $e = $this->hired();
        $this->svc->store($e->person, UploadedFile::fake()->image('face.jpg'));

        $this->actingAs(User::factory()->forCompanies([$e->company])->withPermissions('employee.view')->create(['must_change_password' => false]))
            ->get(route('persons.photo', $e->person->fresh()))
            ->assertOk();
    }

    public function test_personal_tab_can_upload_and_remove_photo(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(Personal::class, ['employment' => $e])
            ->set('photo', UploadedFile::fake()->image('face.jpg'))
            ->call('uploadPhoto')
            ->assertSee('Photo saved.')
            ->assertSeeHtml(route('persons.photo', $e->person_id, absolute: false).'?v=');

        $person = $e->person->fresh();
        $this->assertNotNull($person->photo_path);
        Storage::disk('private')->assertExists($person->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        Livewire::test(Personal::class, ['employment' => $e->fresh()])
            ->call('removePhoto')
            ->assertSee('Photo removed.');

        $this->assertNull($e->person->fresh()->photo_path);
    }
}
