<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmokeTest extends TestCase
{
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
}
