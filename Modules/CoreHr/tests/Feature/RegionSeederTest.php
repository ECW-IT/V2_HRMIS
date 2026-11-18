<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Database\Seeders\RegionSeeder;
use Modules\CoreHr\Models\Region;
use Tests\TestCase;

class RegionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_upserts_v1_region_names(): void
    {
        $this->seed(RegionSeeder::class);

        $this->assertSame(
            ['Buddhism', 'Christian', 'Muslim', 'Hindu'],
            Region::activeNames(),
        );
        $this->assertSame(4, Region::query()->count());

        $this->seed(RegionSeeder::class);

        $this->assertSame(4, Region::query()->count());
        $this->assertSame('Buddhism', Region::query()->where('code', 'BUDDHISM')->value('name'));
    }
}
