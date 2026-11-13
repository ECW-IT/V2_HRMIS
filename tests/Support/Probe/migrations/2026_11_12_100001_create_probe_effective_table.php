<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probe_effective', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('label');
            $table->timestamps();

            $table->unique(['owner_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probe_effective');
    }
};
