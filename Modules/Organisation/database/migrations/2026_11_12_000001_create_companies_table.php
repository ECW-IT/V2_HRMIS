<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->string('employee_no_prefix', 10)->unique();
            $table->unsignedTinyInteger('employee_no_padding')->default(3);
            $table->unsignedInteger('next_employee_seq')->default(1);
            $table->string('epf_employer_no', 30)->nullable();
            $table->string('etf_employer_no', 30)->nullable();
            $table->string('tin', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
