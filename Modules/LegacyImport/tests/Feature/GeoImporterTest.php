<?php

namespace Modules\LegacyImport\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Models\District;
use Modules\CoreHr\Models\GnDivision;
use Modules\LegacyImport\Services\GeoImporter;
use Tests\TestCase;

class GeoImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_upserts_districts_and_gn_divisions_by_code(): void
    {
        $importer = app(GeoImporter::class);

        $first = $importer->import(
            [
                ['code' => 'COL', 'name' => 'Colombo', 'latitude' => 6.9, 'longitude' => 79.8],
                ['code' => 'GAM', 'name' => 'Gampaha'],
            ],
            [
                ['district_code' => 'COL', 'code' => 'GN001', 'name' => 'Fort'],
                ['district_code' => 'GAM', 'code' => 'GN002', 'name' => 'Nittambuwa North'],
            ],
        );

        $this->assertSame(2, $first['districts']);
        $this->assertSame(2, $first['gn_divisions']);
        $this->assertSame(2, District::query()->count());
        $this->assertSame(2, GnDivision::query()->count());

        $importer->import(
            [
                ['code' => 'COL', 'name' => 'Colombo City'],
            ],
            [
                ['district_code' => 'COL', 'code' => 'GN001', 'name' => 'Fort GN'],
            ],
        );

        $this->assertSame(2, District::query()->count());
        $this->assertSame('Colombo City', District::query()->where('code', 'COL')->value('name'));
        $this->assertSame('Fort GN', GnDivision::query()->where('code', 'GN001')->value('name'));
        $this->assertSame(['Fort GN'], GnDivision::namesForDistrictName('Colombo City'));
    }

    public function test_import_from_sql_dumps_upserts_by_code(): void
    {
        $base = dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'geo';
        $importer = app(GeoImporter::class);

        $first = $importer->importFromSqlDumps(
            $base.DIRECTORY_SEPARATOR.'districts.sql',
            $base.DIRECTORY_SEPARATOR.'gn_divisions.sql',
        );

        $this->assertSame(2, $first['districts']);
        $this->assertSame(2, $first['gn_divisions']);
        $this->assertSame('Colombo', District::query()->where('code', 'LK11')->value('name'));
        $this->assertSame("Mattegoda Central 'A'", GnDivision::query()->where('code', 'LK1201005')->value('name'));
        $this->assertSame(['Sammanthranapura'], GnDivision::namesForDistrictName('Colombo'));

        $second = $importer->importFromSqlDumps(
            $base.DIRECTORY_SEPARATOR.'districts.sql',
            $base.DIRECTORY_SEPARATOR.'gn_divisions.sql',
        );

        $this->assertSame(2, $second['districts']);
        $this->assertSame(2, $second['gn_divisions']);
        $this->assertSame(2, District::query()->count());
        $this->assertSame(2, GnDivision::query()->count());
    }
}
