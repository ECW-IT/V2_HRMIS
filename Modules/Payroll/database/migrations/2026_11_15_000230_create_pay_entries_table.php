<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status', 10);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['pay_period_id', 'employment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_entries');
    }
};
