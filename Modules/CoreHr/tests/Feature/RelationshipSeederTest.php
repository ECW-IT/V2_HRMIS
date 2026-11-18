<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Database\Seeders\RelationshipSeeder;
use Modules\CoreHr\Models\Relationship;
use Tests\TestCase;

class RelationshipSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_upserts_v1_relationship_names(): void
    {
        $this->seed(RelationshipSeeder::class);

        $this->assertSame(
            ['Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister'],
            Relationship::activeNames(),
        );
        $this->assertSame(8, Relationship::query()->count());

        $this->seed(RelationshipSeeder::class);

        $this->assertSame(8, Relationship::query()->count());
        $this->assertSame('Wife', Relationship::query()->where('code', 'WIFE')->value('name'));
    }
}
