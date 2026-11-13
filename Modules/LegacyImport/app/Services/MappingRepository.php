<?php

namespace Modules\LegacyImport\Services;

use RuntimeException;

final class MappingRepository
{
    /** @var array<string, list<string>> */
    private const HEADERS = [
        'company_map' => ['legacy_branch_id', 'company_code', 'location_code'],
        'org_unit_map' => ['legacy_department_id', 'legacy_section_id', 'company_code', 'org_unit_code'],
        'job_map' => ['legacy_job_title_id', 'job_code'],
        'grade_assignment' => ['legacy_hrm_id', 'grade_code'],
        'device_groups' => ['legacy_branch_id', 'device_group'],
        'document_type_map' => ['legacy_attachment_type_id', 'document_type_code'],
        'org_units' => ['company_code', 'code', 'name', 'type', 'parent_code'],
        'jobs' => ['code', 'name'],
    ];

    /** @var array<string, list<array<string, string>>>|null */
    private ?array $cache = null;

    /** @return list<array<string, string>> */
    public function rows(string $key): array
    {
        $this->loadAll();

        return $this->cache[$key] ?? [];
    }

    public function companyCodeForBranch(int|string|null $branchId): ?string
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        foreach ($this->rows('company_map') as $row) {
            if ((string) $row['legacy_branch_id'] === (string) $branchId) {
                return $row['company_code'];
            }
        }

        return null;
    }

    public function locationCodeForBranch(int|string|null $branchId): ?string
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        foreach ($this->rows('company_map') as $row) {
            if ((string) $row['legacy_branch_id'] === (string) $branchId) {
                return $row['location_code'];
            }
        }

        return null;
    }

    public function deviceGroupForBranch(int|string|null $branchId): ?string
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        foreach ($this->rows('device_groups') as $row) {
            if ((string) $row['legacy_branch_id'] === (string) $branchId) {
                return $row['device_group'];
            }
        }

        return null;
    }

    public function jobCodeForTitle(int|string|null $jobTitleId): ?string
    {
        if ($jobTitleId === null || $jobTitleId === '') {
            return null;
        }

        foreach ($this->rows('job_map') as $row) {
            if ((string) $row['legacy_job_title_id'] === (string) $jobTitleId) {
                return $row['job_code'];
            }
        }

        return null;
    }

    public function gradeCodeForEmployee(int|string $legacyHrmId): ?string
    {
        foreach ($this->rows('grade_assignment') as $row) {
            if ((string) $row['legacy_hrm_id'] === (string) $legacyHrmId) {
                return $row['grade_code'];
            }
        }

        return null;
    }

    public function documentTypeCode(int|string|null $attachmentTypeId): ?string
    {
        if ($attachmentTypeId === null || $attachmentTypeId === '') {
            return null;
        }

        foreach ($this->rows('document_type_map') as $row) {
            if ((string) $row['legacy_attachment_type_id'] === (string) $attachmentTypeId) {
                return $row['document_type_code'];
            }
        }

        return null;
    }

    /**
     * Section-specific row wins over department-wide (blank section) row.
     */
    public function orgUnitCode(int|string|null $departmentId, int|string|null $sectionId, ?string $companyCode): ?string
    {
        if ($departmentId === null || $departmentId === '' || $companyCode === null) {
            return null;
        }

        $departmentMatch = null;

        foreach ($this->rows('org_unit_map') as $row) {
            if ((string) $row['legacy_department_id'] !== (string) $departmentId) {
                continue;
            }
            if ($row['company_code'] !== $companyCode) {
                continue;
            }

            $mappedSection = trim((string) $row['legacy_section_id']);
            if ($sectionId !== null && $sectionId !== '' && $mappedSection === (string) $sectionId) {
                return $row['org_unit_code'];
            }
            if ($mappedSection === '') {
                $departmentMatch = $row['org_unit_code'];
            }
        }

        return $departmentMatch;
    }

    public function forget(): void
    {
        $this->cache = null;
    }

    private function loadAll(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];

        foreach (array_keys(self::HEADERS) as $key) {
            $this->cache[$key] = $this->readCsv($key);
        }
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $key): array
    {
        $path = config('legacy_import.'.$key);
        $fileName = basename((string) $path);

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException("Mapping file missing: {$fileName}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Mapping file missing: {$fileName}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException("Mapping file {$fileName} line 1: wrong header.");
            }

            $header = array_map(fn ($h) => trim((string) $h), $header);
            $expected = self::HEADERS[$key];
            if ($header !== $expected) {
                throw new RuntimeException("Mapping file {$fileName} line 1: wrong header.");
            }

            $rows = [];
            $line = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $line++;
                if ($this->rowIsEmpty($data)) {
                    continue;
                }

                if (count($data) < count($expected)) {
                    $data = array_pad($data, count($expected), '');
                }

                $assoc = [];
                foreach ($expected as $i => $col) {
                    $assoc[$col] = trim((string) ($data[$i] ?? ''));
                }

                $this->assertKeysPresent($key, $fileName, $line, $assoc);
                $rows[] = $assoc;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @param  array<int, string|null>  $data */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, string>  $assoc */
    private function assertKeysPresent(string $key, string $fileName, int $line, array $assoc): void
    {
        $required = match ($key) {
            'company_map' => ['legacy_branch_id', 'company_code', 'location_code'],
            'org_unit_map' => ['legacy_department_id', 'company_code', 'org_unit_code'],
            'job_map' => ['legacy_job_title_id', 'job_code'],
            'grade_assignment' => ['legacy_hrm_id', 'grade_code'],
            'device_groups' => ['legacy_branch_id', 'device_group'],
            'document_type_map' => ['legacy_attachment_type_id', 'document_type_code'],
            'org_units' => ['company_code', 'code', 'name', 'type'],
            'jobs' => ['code', 'name'],
            default => [],
        };

        foreach ($required as $col) {
            if (($assoc[$col] ?? '') === '') {
                throw new RuntimeException("Mapping file {$fileName} line {$line}: blank key ({$col}).");
            }
        }
    }
}
