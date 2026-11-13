<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->foreignId('employee_category_id')
                ->nullable()
                ->after('previous_employment_id')
                ->constrained('employee_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_category_id');
        });
    }
};
