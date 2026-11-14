<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timecard_decision_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timecard_id')->constrained('timecards')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('work_date');
            $table->string('kind', 16);
            $table->unsignedInteger('old_value')->nullable();
            $table->unsignedInteger('new_value')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timecard_decision_logs');
    }
};
