<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 50)->unique();
            $table->string('name', 100);
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('device_group', 20);
            $table->string('ip_address', 45)->nullable();
            $table->char('api_token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->restrictOnDelete();
            $table->unsignedInteger('device_user_id');
            $table->dateTime('punched_at');
            $table->foreignId('employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->string('resolution', 10);
            $table->unsignedTinyInteger('verify_mode')->nullable();
            $table->unsignedTinyInteger('raw_state')->nullable();
            $table->dateTime('received_at');

            $table->unique(['device_id', 'device_user_id', 'punched_at']);
            $table->index(['employment_id', 'punched_at']);
            $table->index(['resolution', 'device_id']);
        });

        Schema::create('punch_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->restrictOnDelete();
            $table->json('payload');
            $table->string('reason', 20);
            $table->dateTime('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_rejections');
        Schema::dropIfExists('punches');
        Schema::dropIfExists('devices');
    }
};
