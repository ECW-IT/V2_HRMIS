<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('province', 50)->nullable()->after('nationality');
            $table->string('electorate_district', 50)->nullable()->after('gn_division');
            $table->decimal('distance_km', 6, 1)->nullable()->after('electorate_district');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['province', 'electorate_district', 'distance_km']);
        });
    }
};
