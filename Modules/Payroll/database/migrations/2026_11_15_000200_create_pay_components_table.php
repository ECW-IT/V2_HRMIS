<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('kind', 12);
            $table->string('method', 20);
            $table->unsignedTinyInteger('sheet');
            $table->boolean('epf_liable')->default(false);
            $table->boolean('taxable')->default(false);
            $table->string('fund', 12)->nullable();
            $table->unsignedTinyInteger('night_base_hours')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_components');
    }
};
