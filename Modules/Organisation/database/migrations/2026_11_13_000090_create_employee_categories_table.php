<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_key', 150)->unique();
            $table->boolean('is_active')->default(true);

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

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_categories');
    }
};
