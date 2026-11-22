<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->boolean('hostel_available')->default(false)->after('payroll_active');
            $table->boolean('food_breakfast')->default(false)->after('hostel_available');
            $table->boolean('food_lunch')->default(false)->after('food_breakfast');
            $table->boolean('food_dinner')->default(false)->after('food_lunch');
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn(['hostel_available', 'food_breakfast', 'food_lunch', 'food_dinner']);
        });
    }
};
