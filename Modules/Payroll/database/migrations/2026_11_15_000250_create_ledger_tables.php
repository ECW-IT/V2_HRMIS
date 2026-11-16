<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrears_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('kind', 12);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employment_id', 'kind', 'pay_run_id']);
        });

        Schema::create('fund_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('fund', 12);
            $table->string('kind', 12);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employment_id', 'fund', 'kind', 'pay_run_id'], 'fund_ledger_emp_fund_kind_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_ledger');
        Schema::dropIfExists('arrears_ledger');
    }
};
