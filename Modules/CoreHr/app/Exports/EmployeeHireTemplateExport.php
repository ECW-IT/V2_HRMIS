<?php

namespace Modules\CoreHr\Exports;

use App\Support\Company\CompanyContext;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Models\Shift;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\Region;
use Modules\CoreHr\Support\SriLankaGeo;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayComponent;

final class EmployeeHireTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public const EXAMPLE_NIC = '198810099999';

    /** @return list<string> */
    public static function hireHeadings(): array
    {
        return [
            'nic',
            'passport_no',
            'first_name',
            'middle_name',
            'last_name',
            'name_with_initials',
            'calling_name',
            'full_name',
            'date_of_birth',
            'gender',
            'marital_status',
            'nationality',
            'driving_licence_no',
            'landline_no',
            'office_no',
            'region',
            'arrive_work_from',
            'province',
            'electorate_district',
            'gn_division',
            'distance_km',
            'mobile',
            'email',
            'address_line_1',
            'address_line_2',
            'city',
            'district',
            'postal_code',
            'type',
            'hire_date',
            'confirmation_due_date',
            'resign_date',
            'employee_no',
            'epf_no',
            'org_unit',
            'designation',
            'location',
            'shift',
            'employee_category',
            'hrmis_active',
            'attendance_active',
            'payroll_active',
            'basic_salary',
            'ot_rate_mode',
            'ot_rate',
            'attendance_type',
            'earning_type',
            'earning_amount',
            'deduction_type',
            'deduction_amount',
            'hostel_available',
            'food_breakfast',
            'food_lunch',
            'food_dinner',
            'paid_in_cash',
            'bank',
            'branch',
            'account_no',
            'account_name',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
            'ot_mode',
            'late_deduction',
            'device_group',
            'fingerprint_id',
            'reports_to_employee_no',
        ];
    }

    public function sheets(): array
    {
        $companyId = CompanyContext::id();

        $orgUnits = $companyId === null
            ? []
            : OrgUnit::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all();

        $designations = Designation::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $locations = Location::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $shifts = Shift::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $categories = EmployeeCategory::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $banks = Bank::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $regions = Region::activeNames();
        $provinces = SriLankaGeo::provinces();
        $districts = SriLankaGeo::districts();
        $deviceGroups = DeviceGroup::query()->where('is_active', true)->orderBy('code')->pluck('code')->all();
        $attachableMethods = [
            CalcMethod::FIXED->value,
            CalcMethod::PROPORTIONAL->value,
            CalcMethod::QUANTITY_RATE->value,
            CalcMethod::NIGHT_UNITS->value,
        ];
        $earningCodes = PayComponent::query()
            ->where('is_active', true)
            ->where('kind', ComponentKind::EARNING)
            ->whereIn('method', $attachableMethods)
            ->orderBy('code')
            ->pluck('code')
            ->all();
        $deductionCodes = PayComponent::query()
            ->where('is_active', true)
            ->where('kind', ComponentKind::DEDUCTION)
            ->whereIn('method', $attachableMethods)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $arriveOptions = array_map(
            static fn (ArriveWorkFrom $case) => $case->value,
            ArriveWorkFrom::cases()
        );

        $example = [
            self::EXAMPLE_NIC,
            '',
            'Sample',
            '',
            'Employee',
            'S. Employee',
            'Sample',
            'Sample Employee',
            '1988-04-09',
            'MALE',
            'SINGLE',
            'Sri Lankan',
            '',
            '',
            '',
            $regions[0] ?? 'Buddhism',
            ArriveWorkFrom::HOME->value,
            $provinces[0] ?? 'Western',
            $districts[0] ?? 'Colombo',
            '',
            '',
            '0771234567',
            '',
            '123 Sample Road',
            '',
            'Colombo',
            '',
            '',
            'PERMANENT',
            date('Y-m-d'),
            '',
            '',
            '',
            '',
            $orgUnits[0] ?? 'Production',
            $designations[0] ?? 'Helper',
            $locations[0] ?? 'Factory',
            $shifts[0] ?? 'Day shift',
            $categories[0] ?? '',
            'Yes',
            'Yes',
            'Yes',
            '50000.00',
            'CALCULATED',
            '',
            '',
            '',
            '',
            '',
            '',
            'No',
            'No',
            'No',
            'No',
            'Yes',
            '',
            '',
            '',
            '',
            'Jane Doe',
            'Spouse',
            '0777654321',
            'NONE',
            'No',
            $deviceGroups[0] ?? '',
            '',
            '',
        ];

        $listHeadings = [
            'org_unit',
            'designation',
            'location',
            'shift',
            'employee_category',
            'bank',
            'type',
            'gender',
            'marital_status',
            'ot_mode',
            'ot_rate_mode',
            'yes_no',
            'region',
            'arrive_work_from',
            'province',
            'electorate_district',
            'device_group',
            'earning_type',
            'deduction_type',
        ];

        $types = ['PERMANENT', 'PROBATION', 'INTERN', 'CONTRACT'];
        $genders = ['MALE', 'FEMALE'];
        $maritals = ['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'];
        $otModes = ['NONE', 'MORNING', 'EVENING', 'BOTH'];
        $otRateModes = ['CALCULATED', 'FIXED'];
        $yesNo = ['Yes', 'No'];

        $max = max(
            count($orgUnits),
            count($designations),
            count($locations),
            count($shifts),
            count($categories),
            count($banks),
            count($regions),
            count($provinces),
            count($districts),
            count($deviceGroups),
            count($earningCodes),
            count($deductionCodes),
            count($arriveOptions),
            4,
        );

        $listRows = [];
        for ($i = 0; $i < $max; $i++) {
            $listRows[] = [
                $orgUnits[$i] ?? '',
                $designations[$i] ?? '',
                $locations[$i] ?? '',
                $shifts[$i] ?? '',
                $categories[$i] ?? '',
                $banks[$i] ?? '',
                $types[$i] ?? '',
                $genders[$i] ?? '',
                $maritals[$i] ?? '',
                $otModes[$i] ?? '',
                $otRateModes[$i] ?? '',
                $yesNo[$i] ?? '',
                $regions[$i] ?? '',
                $arriveOptions[$i] ?? '',
                $provinces[$i] ?? '',
                $districts[$i] ?? '',
                $deviceGroups[$i] ?? '',
                $earningCodes[$i] ?? '',
                $deductionCodes[$i] ?? '',
            ];
        }

        return [
            new HireTemplateSheet('Hires', self::hireHeadings(), [$example]),
            new HireTemplateSheet('Lists', $listHeadings, $listRows),
        ];
    }
}
