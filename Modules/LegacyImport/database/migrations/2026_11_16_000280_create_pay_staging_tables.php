<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stg_pay_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->unsignedInteger('legacy_profile_id');
            $table->string('emp_no', 50);
            $table->string('company_code', 10)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->string('ot_rate_mode', 20)->nullable();
            $table->string('attendance_type', 20)->nullable();
            $table->unsignedTinyInteger('hostal')->default(0);
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('raw')->nullable();
            $table->boolean('blocked')->default(false);
            $table->boolean('loaded')->default(false);
            $table->text('load_error')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'company_id']);
            $table->index(['import_run_id', 'emp_no']);
        });

        Schema::create('stg_pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignId('stg_pay_profile_id')->constrained('stg_pay_profiles')->restrictOnDelete();
            $table->unsignedInteger('legacy_adju_id');
            $table->unsignedInteger('legacy_adjustment_type_id');
            $table->string('legacy_name', 100)->nullable();
            $table->string('v2_code', 30)->nullable();
            $table->string('action', 20)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->boolean('skip_zero')->default(false);
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->index(['import_run_id', 'stg_pay_profile_id']);
        });

        Schema::create('stg_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignId('stg_pay_profile_id')->constrained('stg_pay_profiles')->restrictOnDelete();
            $table->unsignedInteger('legacy_loan_id');
            $table->unsignedInteger('loan_type_id')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedInteger('approved_installments')->nullable();
            $table->unsignedTinyInteger('if_approved')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedTinyInteger('paid')->nullable();
            $table->json('unpaid_schedule')->nullable();
            $table->decimal('outstanding', 12, 2)->nullable();
            $table->boolean('blocked')->default(false);
            $table->boolean('loaded')->default(false);
            $table->timestamps();

            $table->index(['import_run_id', 'stg_pay_profile_id']);
        });

        Schema::create('stg_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignId('stg_pay_profile_id')->constrained('stg_pay_profiles')->restrictOnDelete();
            $table->unsignedInteger('legacy_advance_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('unpaid_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('is_paid')->nullable();
            $table->date('paid_date')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedInteger('converted_to_loan_id')->nullable();
            $table->json('unpaid_deductions')->nullable();
            $table->boolean('blocked')->default(false);
            $table->boolean('loaded')->default(false);
            $table->timestamps();

            $table->index(['import_run_id', 'stg_pay_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stg_advances');
        Schema::dropIfExists('stg_loans');
        Schema::dropIfExists('stg_pay_components');
        Schema::dropIfExists('stg_pay_profiles');
    }
};
