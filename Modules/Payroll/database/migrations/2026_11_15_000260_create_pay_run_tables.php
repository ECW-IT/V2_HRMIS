<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('pay_period_id')->unique()->constrained('pay_periods')->restrictOnDelete();
            $table->string('status', 12);
            $table->unsignedSmallInteger('time_summary_version')->nullable();
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->decimal('gross_sheet1', 14, 2)->default(0);
            $table->decimal('gross_sheet2', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('employer_total', 14, 2)->default(0);
            $table->decimal('carried_forward_out', 14, 2)->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pay_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_id')->constrained('pay_runs')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('status', 8);
            $table->string('error', 255)->nullable();
            $table->string('employee_no', 20);
            $table->string('name', 150);
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr_jobs')->restrictOnDelete();
            $table->string('employment_type', 12);
            $table->boolean('epf_liable');
            $table->string('epf_no', 20)->nullable();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('ot_rate', 12, 2);
            $table->string('extra_day_method', 10)->nullable();
            $table->string('attendance_tier_code', 10)->nullable();
            $table->decimal('expected_days', 4, 1);
            $table->decimal('credited_days', 5, 2);
            $table->decimal('extra_days', 5, 2);
            $table->decimal('nopay_days', 5, 2);
            $table->unsignedInteger('late_minutes_chargeable');
            $table->unsignedInteger('ot_minutes');
            $table->decimal('carried_forward_in', 12, 2);
            $table->boolean('is_cash');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->restrictOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->restrictOnDelete();
            $table->string('account_no', 30)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->decimal('gross_sheet1', 12, 2)->default(0);
            $table->decimal('gross_sheet2', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('carried_forward_out', 12, 2)->default(0);
            $table->decimal('epf_base', 12, 2)->default(0);
            $table->decimal('taxable_pay', 12, 2)->default(0);
            $table->decimal('employer_total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['pay_run_id', 'employment_id']);
        });

        Schema::create('pay_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_employee_id')->constrained('pay_run_employees')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('kind', 12);
            $table->unsignedTinyInteger('sheet');
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_override')->default(false);
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();

            $table->index('pay_run_employee_id');
            $table->index('code');
        });

        Schema::create('pay_run_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_id')->constrained('pay_runs')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['pay_run_id', 'employment_id', 'pay_component_id'], 'pay_run_overrides_active_idx');
        });

        Schema::create('pay_run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_id')->constrained('pay_runs')->restrictOnDelete();
            $table->string('event', 12);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('salary_advances', function (Blueprint $table) {
            $table->foreign('recovered_pay_run_id')->references('id')->on('pay_runs')->restrictOnDelete();
        });

        Schema::table('loan_instalments', function (Blueprint $table) {
            $table->foreign('pay_run_id')->references('id')->on('pay_runs')->restrictOnDelete();
        });

        Schema::table('arrears_ledger', function (Blueprint $table) {
            $table->foreign('pay_run_id')->references('id')->on('pay_runs')->restrictOnDelete();
        });

        Schema::table('fund_ledger', function (Blueprint $table) {
            $table->foreign('pay_run_id')->references('id')->on('pay_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fund_ledger', function (Blueprint $table) {
            $table->dropForeign(['pay_run_id']);
        });
        Schema::table('arrears_ledger', function (Blueprint $table) {
            $table->dropForeign(['pay_run_id']);
        });
        Schema::table('loan_instalments', function (Blueprint $table) {
            $table->dropForeign(['pay_run_id']);
        });
        Schema::table('salary_advances', function (Blueprint $table) {
            $table->dropForeign(['recovered_pay_run_id']);
        });

        Schema::dropIfExists('pay_run_events');
        Schema::dropIfExists('pay_run_overrides');
        Schema::dropIfExists('pay_run_lines');
        Schema::dropIfExists('pay_run_employees');
        Schema::dropIfExists('pay_runs');
    }
};
