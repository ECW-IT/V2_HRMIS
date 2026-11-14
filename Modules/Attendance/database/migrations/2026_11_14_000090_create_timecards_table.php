<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedSmallInteger('punch_count')->default(0);
            $table->string('day_type', 6);
            $table->decimal('day_value', 3, 2);
            $table->boolean('short_leave')->default(false);
            $table->unsignedSmallInteger('late_in_minutes')->default(0);
            $table->unsignedSmallInteger('early_out_minutes')->default(0);
            $table->unsignedSmallInteger('ot_morning_minutes')->default(0);
            $table->unsignedSmallInteger('ot_evening_minutes')->default(0);
            $table->boolean('is_holiday')->default(false);
            $table->string('exception', 16)->nullable();
            $table->dateTime('computed_at');
            $table->boolean('late_waived')->default(false);
            $table->unsignedSmallInteger('ot_approved_minutes')->nullable();
            $table->foreignId('ot_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ot_decided_at')->nullable();
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->decimal('leave_days', 3, 2)->default(0);
            $table->timestamps();

            $table->unique(['employment_id', 'work_date']);
            $table->index(['pay_period_id', 'employment_id']);
            $table->index(['company_id', 'work_date']);
            $table->index('exception');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timecards');
    }
};
