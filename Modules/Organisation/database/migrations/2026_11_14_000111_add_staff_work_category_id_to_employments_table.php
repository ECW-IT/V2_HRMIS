<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->foreignId('staff_work_category_id')
                ->nullable()
                ->after('employee_category_id')
                ->constrained('staff_work_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_work_category_id');
        });
    }
};
