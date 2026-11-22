<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->boolean('hrmis_active')->default(true)->after('epf_no');
            $table->boolean('attendance_active')->default(true)->after('hrmis_active');
            $table->boolean('payroll_active')->default(true)->after('attendance_active');
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn(['hrmis_active', 'attendance_active', 'payroll_active']);
        });
    }
};
