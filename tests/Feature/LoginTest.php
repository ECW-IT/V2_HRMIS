<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_reaches_dashboard(): void
    {
        $u = User::factory()->allCompanies()->create(['password' => Hash::make('secret-pass-1'), 'must_change_password' => false]);
        Livewire::test(Login::class)->set('email', $u->email)->set('password', 'secret-pass-1')->call('login')->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_wrong_password_is_refused(): void
    {
        $u = User::factory()->create(['password' => Hash::make('secret-pass-1')]);
        Livewire::test(Login::class)->set('email', $u->email)->set('password', 'nope')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_gets_the_generic_message(): void
    {
        $u = User::factory()->inactive()->create(['password' => Hash::make('secret-pass-1')]);
        Livewire::test(Login::class)
            ->set('email', $u->email)
            ->set('password', 'secret-pass-1')
            ->call('login')
            ->assertHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_user_who_must_change_password_is_redirected(): void
    {
        $u = User::factory()->allCompanies()->create(['must_change_password' => true]);
        $this->actingAs($u)->get('/')->assertRedirect(route('password.change'));
    }
}
