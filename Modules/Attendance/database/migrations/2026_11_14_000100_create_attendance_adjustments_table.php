<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('work_date');
            $table->string('type', 10);
            $table->dateTime('punched_at')->nullable();
            $table->foreignId('punch_id')->nullable()->constrained('punches')->restrictOnDelete();
            $table->string('reason', 255);
            $table->string('status', 10);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employment_id', 'work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
