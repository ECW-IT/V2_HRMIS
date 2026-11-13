<?php

namespace Modules\Organisation\Services;

use Modules\CoreHr\Exceptions\InvalidEmploymentData;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

final class CompanyService
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     employee_no_prefix: string,
     *     employee_no_padding: int,
     *     epf_employer_no?: ?string,
     *     etf_employer_no?: ?string,
     *     tin?: ?string,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): Company
    {
        return Company::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'employee_no_prefix' => $data['employee_no_prefix'],
            'employee_no_padding' => $data['employee_no_padding'],
            'next_employee_seq' => 1,
            'epf_employer_no' => $data['epf_employer_no'] ?? null,
            'etf_employer_no' => $data['etf_employer_no'] ?? null,
            'tin' => $data['tin'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     employee_no_prefix: string,
     *     employee_no_padding: int,
     *     epf_employer_no?: ?string,
     *     etf_employer_no?: ?string,
     *     tin?: ?string,
     *     is_active?: bool
     * }  $data
     */
    public function update(Company $company, array $data): Company
    {
        $prefixChanged = array_key_exists('employee_no_prefix', $data)
            && (string) $data['employee_no_prefix'] !== (string) $company->employee_no_prefix;
        $paddingChanged = array_key_exists('employee_no_padding', $data)
            && (int) $data['employee_no_padding'] !== (int) $company->employee_no_padding;

        if (($prefixChanged || $paddingChanged) && Employment::query()->where('company_id', $company->id)->exists()) {
            throw new InvalidEmploymentData('Employee number prefix and padding cannot change once the company has employments.');
        }

        $company->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'employee_no_prefix' => $data['employee_no_prefix'],
            'employee_no_padding' => $data['employee_no_padding'],
            'epf_employer_no' => $data['epf_employer_no'] ?? null,
            'etf_employer_no' => $data['etf_employer_no'] ?? null,
            'tin' => $data['tin'] ?? null,
            'is_active' => $data['is_active'] ?? $company->is_active,
        ]);

        return $company->fresh();
    }

    public function deactivate(Company $company): void
    {
        $company->update(['is_active' => false]);
    }

    public function activate(Company $company): void
    {
        $company->update(['is_active' => true]);
    }
}
