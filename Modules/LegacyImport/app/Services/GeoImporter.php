<?php

namespace Modules\LegacyImport\Services;

use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Models\District;
use Modules\CoreHr\Models\GnDivision;
use RuntimeException;

final class GeoImporter
{
    /**
     * Upsert districts and GN divisions into V2.
     *
     * @param  list<array{code: string, name: string, latitude?: float|null, longitude?: float|null}>  $districts
     * @param  list<array{district_code: string, code: string, name: string, latitude?: float|null, longitude?: float|null}>  $gnDivisions
     * @return array{districts: int, gn_divisions: int}
     */
    public function import(array $districts, array $gnDivisions): array
    {
        $districtCount = 0;
        $codes = [];

        foreach ($districts as $row) {
            $code = (string) $row['code'];
            District::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => (string) $row['name'],
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ],
            );
            $codes[$code] = true;
            $districtCount++;
        }

        $byCode = District::query()
            ->whereIn('code', array_keys($codes))
            ->pluck('id', 'code');

        $gnCount = 0;
        foreach ($gnDivisions as $row) {
            $districtId = $byCode[(string) $row['district_code']] ?? null;
            if ($districtId === null) {
                $districtId = District::query()->where('code', (string) $row['district_code'])->value('id');
            }
            if ($districtId === null) {
                continue;
            }

            GnDivision::query()->updateOrCreate(
                ['code' => (string) $row['code']],
                [
                    'district_id' => (int) $districtId,
                    'name' => (string) $row['name'],
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ],
            );
            $gnCount++;
        }

        return ['districts' => $districtCount, 'gn_divisions' => $gnCount];
    }

    /** Read-only copy from the legacy_hrm connection into V2. */
    public function importFromLegacy(): array
    {
        $legacyDistricts = DB::connection('legacy_hrm')
            ->table('districts')
            ->orderBy('id')
            ->get(['code', 'name', 'latitude', 'longitude']);

        $legacyGns = DB::connection('legacy_hrm')
            ->table('gn_divisions as g')
            ->join('districts as d', 'd.id', '=', 'g.district_id')
            ->orderBy('g.id')
            ->get([
                'd.code as district_code',
                'g.code',
                'g.name',
                'g.latitude',
                'g.longitude',
            ]);

        $districts = $legacyDistricts->map(fn ($row) => [
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
        ])->all();

        $gnDivisions = $legacyGns->map(fn ($row) => [
            'district_code' => (string) $row->district_code,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
        ])->all();

        return $this->import($districts, $gnDivisions);
    }

    /**
     * Import from phpMyAdmin-style SQL dumps (INSERT rows only; schema ignored).
     *
     * @return array{districts: int, gn_divisions: int}
     */
    public function importFromSqlDumps(string $districtsPath, string $gnPath): array
    {
        $districtSql = $this->readSqlFile($districtsPath);
        $gnSql = $this->readSqlFile($gnPath);

        $districtRows = $this->parseDistrictInserts($districtSql);
        $idToCode = [];
        $districts = [];

        foreach ($districtRows as $row) {
            $idToCode[$row['id']] = $row['code'];
            $districts[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
            ];
        }

        $gnDivisions = [];
        foreach ($this->parseGnInserts($gnSql) as $row) {
            $districtCode = $idToCode[$row['district_id']] ?? null;
            if ($districtCode === null) {
                continue;
            }

            $gnDivisions[] = [
                'district_code' => $districtCode,
                'code' => $row['code'],
                'name' => $row['name'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
            ];
        }

        return $this->import($districts, $gnDivisions);
    }

    private function readSqlFile(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("SQL dump not found: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read SQL dump: {$path}");
        }

        return $contents;
    }

    /**
     * @return list<array{id: int, code: string, name: string, latitude: float|null, longitude: float|null}>
     */
    private function parseDistrictInserts(string $sql): array
    {
        $blocks = $this->extractInsertValueBlocks($sql, 'districts');
        if ($blocks === []) {
            return [];
        }

        // (id, 'code', 'name', lat, lng, ...) — names may contain escaped quotes
        $string = "(?:\\\\'|[^'])*";
        $pattern = '/\((\d+)\s*,\s*\'('.$string.')\'\s*,\s*\'('.$string.')\'\s*,\s*(NULL|[0-9.+-]+)\s*,\s*(NULL|[0-9.+-]+)/i';

        $rows = [];
        foreach ($blocks as $values) {
            if (! preg_match_all($pattern, $values, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                $rows[] = [
                    'id' => (int) $m[1],
                    'code' => $this->unquoteSqlString($m[2]),
                    'name' => $this->unquoteSqlString($m[3]),
                    'latitude' => $this->nullableFloat($m[4]),
                    'longitude' => $this->nullableFloat($m[5]),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{district_id: int, code: string, name: string, latitude: float|null, longitude: float|null}>
     */
    private function parseGnInserts(string $sql): array
    {
        $blocks = $this->extractInsertValueBlocks($sql, 'gn_divisions');
        if ($blocks === []) {
            return [];
        }

        // (id, district_id, 'code', 'name', lat, lng, ...)
        $string = "(?:\\\\'|[^'])*";
        $pattern = '/\((\d+)\s*,\s*(\d+)\s*,\s*\'('.$string.')\'\s*,\s*\'('.$string.')\'\s*,\s*(NULL|[0-9.+-]+)\s*,\s*(NULL|[0-9.+-]+)/i';

        $rows = [];
        foreach ($blocks as $values) {
            if (! preg_match_all($pattern, $values, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                $rows[] = [
                    'district_id' => (int) $m[2],
                    'code' => $this->unquoteSqlString($m[3]),
                    'name' => $this->unquoteSqlString($m[4]),
                    'latitude' => $this->nullableFloat($m[5]),
                    'longitude' => $this->nullableFloat($m[6]),
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function extractInsertValueBlocks(string $sql, string $table): array
    {
        $pattern = '/INSERT\s+INTO\s+`?'.preg_quote($table, '/').'`?\s*\([^)]+\)\s*VALUES\s*(.+?);/is';
        if (! preg_match_all($pattern, $sql, $matches)) {
            return [];
        }

        return $matches[1];
    }

    private function unquoteSqlString(string $value): string
    {
        return str_replace("\\'", "'", $value);
    }

    private function nullableFloat(string $value): ?float
    {
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        return (float) $value;
    }
}
