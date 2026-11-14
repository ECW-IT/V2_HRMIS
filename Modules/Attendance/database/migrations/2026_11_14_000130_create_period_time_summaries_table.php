<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_time_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->string('attendance_mode', 10);
            $table->unsignedTinyInteger('employed_days');
            $table->decimal('expected_days', 4, 1);
            $table->decimal('worked_days', 5, 2);
            $table->unsignedTinyInteger('full_days');
            $table->unsignedTinyInteger('half_days');
            $table->unsignedTinyInteger('short_days');
            $table->unsignedTinyInteger('short_leave_count');
            $table->decimal('paid_leave_days', 4, 1);
            $table->decimal('unpaid_leave_days', 4, 1);
            $table->decimal('credited_days', 5, 2);
            $table->decimal('extra_days', 5, 2);
            $table->decimal('nopay_days', 5, 2);
            $table->unsignedInteger('late_minutes_total');
            $table->unsignedInteger('late_minutes_chargeable');
            $table->unsignedInteger('ot_normal_minutes');
            $table->unsignedInteger('ot_double_minutes');
            $table->boolean('has_override');
            $table->timestamp('created_at')->nullable();

            $table->unique(['pay_period_id', 'employment_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_time_summaries');
    }
};
