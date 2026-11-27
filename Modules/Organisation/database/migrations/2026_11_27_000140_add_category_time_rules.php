<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->boolean('early_ot')->default(false);
            $table->boolean('evening_ot')->default(false);
            $table->boolean('late_deduction')->default(false);
        });

        Schema::create('employee_category_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained('employee_categories')->restrictOnDelete();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_category_breaks');

        Schema::table('employee_categories', function (Blueprint $table) {
            $table->dropColumn(['early_ot', 'evening_ot', 'late_deduction']);
        });
    }
};
