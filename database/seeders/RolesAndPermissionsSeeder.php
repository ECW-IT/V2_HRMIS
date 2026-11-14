<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'org.view',
            'org.manage',
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.exit',
            'employee.apply-directly',
            'employee.bank.view',
            'employee.bank.manage',
            'employee.document.view',
            'employee.document.manage',
            'attendance.device.manage',
            'attendance.punch.view',
            'shift.manage',
            'calendar.manage',
            'period.manage',
            'period.close',
            'approval.act',
            'import.run',
            'access-control.view',
            'access-control.manage',
            'audit.view',
            'settings.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('super-admin');
        $superAdmin->syncPermissions($permissions);

        $hrManager = Role::findOrCreate('hr-manager');
        $hrManager->syncPermissions(array_values(array_diff($permissions, [
            'access-control.view',
            'access-control.manage',
            'import.run',
        ])));

        $hrOfficer = Role::findOrCreate('hr-officer');
        $hrOfficer->syncPermissions([
            'org.view',
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.document.view',
            'employee.document.manage',
            'attendance.punch.view',
        ]);

        $payrollOfficer = Role::findOrCreate('payroll-officer');
        $payrollOfficer->syncPermissions([
            'org.view',
            'employee.view',
            'employee.bank.view',
            'employee.bank.manage',
            'attendance.punch.view',
            'period.manage',
        ]);

        $auditor = Role::findOrCreate('auditor');
        $auditor->syncPermissions([
            'org.view',
            'employee.view',
            'attendance.punch.view',
            'audit.view',
        ]);
    }
}
