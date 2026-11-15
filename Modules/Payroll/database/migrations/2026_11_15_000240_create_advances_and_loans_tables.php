<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('loan_type_id')->constrained('loan_types')->restrictOnDelete();
            $table->string('loan_no', 20);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('instalments');
            $table->date('first_month');
            $table->string('reason', 255)->nullable();
            $table->string('status', 12);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 50)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'loan_no']);
        });

        Schema::create('loan_instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_month');
            $table->decimal('amount', 12, 2);
            $table->string('status', 10);
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'sequence']);
        });

        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('requested_on');
            $table->string('reason', 255)->nullable();
            $table->string('status', 12);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('payment_reference', 50)->nullable();
            $table->unsignedBigInteger('recovered_pay_run_id')->nullable();
            $table->foreignId('converted_loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('loan_instalments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_types');
    }
};
