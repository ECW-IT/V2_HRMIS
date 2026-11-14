<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Modules\CoreHr\Models\DocumentType;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_database_is_mysql_in_strict_utf8mb4(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('utf8mb4', config('database.connections.mysql.charset'));
        $this->assertTrue(config('database.connections.mysql.strict'));
        $this->assertSame('Asia/Colombo', config('app.timezone'));
    }

    public function test_database_seeder_seeds_document_types(): void
    {
        putenv('ADMIN_INITIAL_PASSWORD=seed-test-password-1');
        $_ENV['ADMIN_INITIAL_PASSWORD'] = 'seed-test-password-1';
        $_SERVER['ADMIN_INITIAL_PASSWORD'] = 'seed-test-password-1';

        $this->seed();

        $this->assertGreaterThan(0, DocumentType::count());
    }

    public function test_reseeding_does_not_reset_the_admin_password(): void
    {
        putenv('ADMIN_INITIAL_PASSWORD=seed-test-password-1');
        $_ENV['ADMIN_INITIAL_PASSWORD'] = 'seed-test-password-1';
        $_SERVER['ADMIN_INITIAL_PASSWORD'] = 'seed-test-password-1';

        $this->seed();

        $admin = User::query()->where('email', 'admin@ecw.local')->sole();
        $admin->update([
            'password' => Hash::make('changed-password-99'),
            'must_change_password' => false,
        ]);

        putenv('ADMIN_INITIAL_PASSWORD=different-initial-password');
        $_ENV['ADMIN_INITIAL_PASSWORD'] = 'different-initial-password';
        $_SERVER['ADMIN_INITIAL_PASSWORD'] = 'different-initial-password';

        $this->seed();

        $admin->refresh();
        $this->assertTrue(Hash::check('changed-password-99', $admin->password));
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue($admin->hasRole('super-admin'));
    }

    public function test_no_route_uses_an_undefined_guard(): void
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                $this->assertStringNotContainsString(
                    'auth:sanctum',
                    (string) $middleware,
                    "Route [{$route->uri()}] still uses auth:sanctum."
                );
            }
        }
    }
}
