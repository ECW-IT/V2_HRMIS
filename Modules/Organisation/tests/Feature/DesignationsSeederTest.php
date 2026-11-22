<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Database\Seeders\DesignationsSeeder;
use Modules\Organisation\Models\Designation;
use Tests\TestCase;

class DesignationsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_designations_and_is_idempotent(): void
    {
        $path = base_path('docs/mapping/designations.csv');
        $this->assertFileExists($path);

        $expected = $this->readCsv($path);
        $this->assertNotEmpty($expected);

        $this->seed(DesignationsSeeder::class);

        $this->assertSame(count($expected), Designation::query()->count());
        $this->assertSame(count($expected), Designation::query()->distinct()->count('code'));

        foreach ($expected as $code => $name) {
            $designation = Designation::query()->where('code', $code)->sole();
            $this->assertSame($name, $designation->name);
            $this->assertTrue($designation->is_active);
        }

        $this->seed(DesignationsSeeder::class);
        $this->assertSame(count($expected), Designation::query()->count());
    }

    /** @return array<string, string> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle);

        $header = fgetcsv($handle);
        $this->assertNotFalse($header);
        $header = array_map(static fn ($h) => trim((string) $h), $header);

        $rows = [];
        while (($cols = fgetcsv($handle)) !== false) {
            if ($cols === [null]) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($cols[$i] ?? ''));
            }

            if (($row['code'] ?? '') === '' || ($row['name'] ?? '') === '') {
                continue;
            }

            $rows[$row['code']] = $row['name'];
        }

        fclose($handle);

        return $rows;
    }
}
