<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ytd_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('tax_year_start');
            $table->unsignedTinyInteger('months_covered');
            $table->decimal('gross', 14, 2);
            $table->decimal('taxable_pay', 14, 2);
            $table->decimal('tax', 14, 2);
            $table->decimal('epf_employee', 14, 2);
            $table->decimal('epf_employer', 14, 2);
            $table->decimal('etf', 14, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employment_id', 'tax_year_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ytd_openings');
    }
};
