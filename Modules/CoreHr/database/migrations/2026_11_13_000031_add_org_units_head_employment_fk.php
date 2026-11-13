<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->foreign('head_employment_id')
                ->references('id')
                ->on('employments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropForeign(['head_employment_id']);
        });
    }
};
