<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('period_expected_days')->delete();

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['pay_period_id']);
        });

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->dropUnique(['pay_period_id', 'grade_id']);
            $table->dropColumn('grade_id');
        });

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_category_id')->after('pay_period_id')->constrained('employee_categories')->restrictOnDelete();
            $table->unique(['pay_period_id', 'employee_category_id']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('period_time_summaries', function (Blueprint $table) {
            $table->foreignId('employee_category_id')->nullable()->after('version')->constrained('employee_categories')->restrictOnDelete();
        });

        DB::statement('
            UPDATE period_time_summaries s
            INNER JOIN employments e ON e.id = s.employment_id
            SET s.employee_category_id = e.employee_category_id
        ');

        Schema::table('period_time_summaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->foreignId('employee_category_id')->nullable()->after('name')->constrained('employee_categories')->restrictOnDelete();
        });

        DB::statement('
            UPDATE pay_run_employees p
            INNER JOIN employments e ON e.id = p.employment_id
            SET p.employee_category_id = e.employee_category_id
        ');

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->foreignId('employee_category_id')->nullable()->after('company_id')->constrained('employee_categories')->restrictOnDelete();
        });

        DB::table('grade_pay_rules')->delete();

        Schema::table('grade_pay_rules', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
        });

        Schema::table('grade_pay_rules', function (Blueprint $table) {
            $table->dropUnique(['grade_id', 'effective_from']);
            $table->dropColumn('grade_id');
        });

        Schema::rename('grade_pay_rules', 'category_pay_rules');

        Schema::table('category_pay_rules', function (Blueprint $table) {
            $table->foreignId('employee_category_id')->after('id')->constrained('employee_categories')->restrictOnDelete();
            $table->unique(['employee_category_id', 'effective_from']);
        });

        Schema::dropIfExists('grades');
    }

    public function down(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('rank')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('category_pay_rules', function (Blueprint $table) {
            $table->dropForeign(['employee_category_id']);
        });

        Schema::table('category_pay_rules', function (Blueprint $table) {
            $table->dropUnique(['employee_category_id', 'effective_from']);
            $table->dropColumn('employee_category_id');
        });

        Schema::rename('category_pay_rules', 'grade_pay_rules');

        Schema::table('grade_pay_rules', function (Blueprint $table) {
            $table->foreignId('grade_id')->after('id')->constrained('grades')->restrictOnDelete();
            $table->unique(['grade_id', 'effective_from']);
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_category_id');
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('company_id')->constrained('grades')->restrictOnDelete();
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_category_id');
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->foreignId('grade_id')->after('name')->constrained('grades')->restrictOnDelete();
        });

        Schema::table('period_time_summaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_category_id');
        });

        Schema::table('period_time_summaries', function (Blueprint $table) {
            $table->foreignId('grade_id')->after('version')->constrained('grades')->restrictOnDelete();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('grade_id')->after('designation_id')->constrained('grades')->restrictOnDelete();
        });

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->dropForeign(['pay_period_id']);
            $table->dropForeign(['employee_category_id']);
        });

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->dropUnique(['pay_period_id', 'employee_category_id']);
            $table->dropColumn('employee_category_id');
        });

        Schema::table('period_expected_days', function (Blueprint $table) {
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->cascadeOnDelete();
            $table->foreignId('grade_id')->after('pay_period_id')->constrained('grades')->restrictOnDelete();
            $table->unique(['pay_period_id', 'grade_id']);
        });
    }
};
