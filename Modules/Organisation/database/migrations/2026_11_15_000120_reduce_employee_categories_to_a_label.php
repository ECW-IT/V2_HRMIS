<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employee_category_duty_schedules');
        Schema::dropIfExists('employee_category_breaks');
        Schema::dropIfExists('break_types');

        Schema::table('employee_categories', function (Blueprint $table) {
            $table->dropColumn([
                'annual_leaves',
                'casual_leaves',
                'medical_leaves',
                'normal_ot_rate',
                'double_ot_rate',
                'no_pay_rate_per_hour',
                'no_pay_rate_per_day',
                'saturday_rate',
                'sunday_rate',
                'payroll_workdays',
                'payroll_workhours',
                'saturday_ot_mode',
                'custom_saturday_ot_type',
                'sunday_ot_mode',
                'custom_sunday_ot_type',
                'on_duty_time',
                'off_duty_time',
                'morning_short_beginning',
                'morning_short_ending',
                'evening_short_beginning',
                'evening_short_ending',
                'halfday_beginning',
                'late_on',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_leaves')->nullable();
            $table->unsignedSmallInteger('casual_leaves')->nullable();
            $table->unsignedSmallInteger('medical_leaves')->nullable();

            $table->decimal('normal_ot_rate', 12, 2)->nullable();
            $table->decimal('double_ot_rate', 12, 2)->nullable();
            $table->decimal('no_pay_rate_per_hour', 12, 2)->nullable();
            $table->decimal('no_pay_rate_per_day', 12, 2)->nullable();
            $table->decimal('saturday_rate', 12, 2)->nullable();
            $table->decimal('sunday_rate', 12, 2)->nullable();

            $table->unsignedSmallInteger('payroll_workdays')->default(0);
            $table->unsignedSmallInteger('payroll_workhours')->default(0);

            $table->unsignedTinyInteger('saturday_ot_mode')->default(0);
            $table->decimal('custom_saturday_ot_type', 4, 2)->nullable();
            $table->unsignedTinyInteger('sunday_ot_mode')->default(0);
            $table->decimal('custom_sunday_ot_type', 4, 2)->nullable();

            $table->time('on_duty_time')->nullable();
            $table->time('off_duty_time')->nullable();
            $table->time('morning_short_beginning')->nullable();
            $table->time('morning_short_ending')->nullable();
            $table->time('evening_short_beginning')->nullable();
            $table->time('evening_short_ending')->nullable();
            $table->time('halfday_beginning')->nullable();
            $table->time('late_on')->nullable();
        });

        Schema::create('break_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_paid')->default(false);
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_category_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained('employee_categories')->restrictOnDelete();
            $table->foreignId('break_type_id')->constrained('break_types')->restrictOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();

            $table->unique(['employee_category_id', 'break_type_id'], 'emp_cat_breaks_cat_type_unique');
        });

        Schema::create('employee_category_duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained('employee_categories')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->time('on_duty_time')->nullable();
            $table->time('off_duty_time')->nullable();
            $table->time('morning_short_beginning')->nullable();
            $table->time('morning_short_ending')->nullable();
            $table->time('evening_short_beginning')->nullable();
            $table->time('evening_short_ending')->nullable();
            $table->time('halfday_beginning')->nullable();
            $table->time('late_on')->nullable();
            $table->timestamps();

            $table->unique(['employee_category_id', 'effective_from'], 'emp_cat_duty_cat_from_unique');
            $table->index(['employee_category_id', 'effective_from', 'effective_to'], 'emp_cat_duty_range_index');
        });
    }
};
