<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('basic_salary', 12, 2);
            $table->string('ot_rate_mode', 10);
            $table->decimal('ot_rate', 12, 2)->nullable();
            $table->string('attendance_tier_code', 10)->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employment_id', 'effective_from']);
        });

        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['employment_id', 'pay_component_id', 'effective_from'], 'emp_components_emp_comp_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_components');
        Schema::dropIfExists('employee_compensations');
    }
};
