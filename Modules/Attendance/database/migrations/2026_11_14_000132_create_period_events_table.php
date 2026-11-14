<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->restrictOnDelete();
            $table->string('event', 10);
            $table->unsignedSmallInteger('version');
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_events');
    }
};
