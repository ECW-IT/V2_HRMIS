<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gn_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('district_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gn_divisions');
    }
};
