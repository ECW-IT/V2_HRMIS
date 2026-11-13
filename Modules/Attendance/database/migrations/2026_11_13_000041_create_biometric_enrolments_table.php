<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('device_group', 20);
            $table->unsignedInteger('device_user_id');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['device_group', 'device_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_enrolments');
    }
};
