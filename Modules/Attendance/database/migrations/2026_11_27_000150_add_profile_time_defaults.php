<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_time_profiles', function (Blueprint $table) {
            $table->string('ot_mode', 10)->nullable()->change();
            $table->boolean('late_deduction')->nullable()->change();
            $table->boolean('break_available')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employment_time_profiles', function (Blueprint $table) {
            $table->dropColumn('break_available');
            $table->string('ot_mode', 10)->nullable(false)->change();
            $table->boolean('late_deduction')->nullable(false)->default(false)->change();
        });
    }
};
