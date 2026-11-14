<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('date');
            $table->string('portion', 8);
            $table->decimal('days', 2, 1);
            $table->string('reason', 255)->nullable();
            $table->foreignId('covering_employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->string('status', 10);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employment_id', 'date']);
            $table->index(['company_id', 'date', 'status']);
        });

        Schema::create('leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->string('kind', 10);
            $table->decimal('days', 4, 1);
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['employment_id', 'leave_type_id', 'pay_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger');
        Schema::dropIfExists('leave_requests');
    }
};
