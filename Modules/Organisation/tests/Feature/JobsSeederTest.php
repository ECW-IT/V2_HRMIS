<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Database\Seeders\JobsSeeder;
use Modules\Organisation\Models\Job;
use Tests\TestCase;

class JobsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_designations_and_is_idempotent(): void
    {
        $expected = [
            'MD' => 'MD',
            'CEO' => 'CEO',
            'ADMIN' => 'Admin',
            'SUPER_ADMIN' => 'Super Admin',
            'ACCOUNTANT' => 'Accountant',
            'EXECUTIVE' => 'Executive',
            'MANAGER' => 'Manager',
            'SALES' => 'Sales',
            'GENERAL_MANAGER' => 'General Manager',
            'TEAM_LEADER' => 'Team Leader',
            'COORDINATOR' => 'Coordinator',
            'DIRECTOR' => 'Director',
            'EMPLOYEE' => 'Employee',
        ];

        $this->seed(JobsSeeder::class);

        $this->assertSame(count($expected), Job::query()->count());

        foreach ($expected as $code => $name) {
            $job = Job::query()->where('code', $code)->sole();
            $this->assertSame($name, $job->name);
            $this->assertTrue($job->is_active);
        }

        $this->seed(JobsSeeder::class);
        $this->assertSame(count($expected), Job::query()->count());
    }
}
