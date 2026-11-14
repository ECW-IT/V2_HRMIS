<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->time('late_from');
            $table->time('morning_cutoff');
            $table->time('half_day_from');
            $table->time('early_leave_from');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });

        Schema::create('employment_time_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->string('attendance_mode', 10);
            $table->string('ot_mode', 10);
            $table->boolean('late_deduction')->default(false);
            $table->timestamps();

            $table->unique(['employment_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_time_profiles');
        Schema::dropIfExists('shift_breaks');
        Schema::dropIfExists('shifts');
    }
};
