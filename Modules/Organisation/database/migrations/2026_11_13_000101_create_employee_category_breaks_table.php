<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_category_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained('employee_categories')->restrictOnDelete();
            $table->foreignId('break_type_id')->constrained('break_types')->restrictOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();

            $table->unique(['employee_category_id', 'break_type_id'], 'emp_cat_breaks_cat_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_category_breaks');
    }
};
