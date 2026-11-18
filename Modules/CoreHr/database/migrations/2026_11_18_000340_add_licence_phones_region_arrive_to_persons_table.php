<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('driving_licence_no', 50)->nullable()->after('nationality');
            $table->string('landline_no', 20)->nullable()->after('driving_licence_no');
            $table->string('office_no', 20)->nullable()->after('landline_no');
            $table->string('region', 50)->nullable()->after('office_no');
            $table->string('arrive_work_from', 20)->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'driving_licence_no',
                'landline_no',
                'office_no',
                'region',
                'arrive_work_from',
            ]);
        });
    }
};
