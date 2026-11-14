<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('field', 30);
            $table->decimal('value', 7, 2);
            $table->string('reason', 255);
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_overrides');
    }
};
