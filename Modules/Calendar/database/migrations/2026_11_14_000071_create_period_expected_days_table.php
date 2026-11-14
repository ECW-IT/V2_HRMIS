<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_expected_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->decimal('days', 4, 1);
            $table->foreignId('set_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['pay_period_id', 'grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_expected_days');
    }
};
