<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->nullable()->after('name_key');
        });

        $level = 1;
        foreach (DB::table('employee_categories')->orderBy('id')->get(['id']) as $row) {
            DB::table('employee_categories')->where('id', $row->id)->update(['level' => $level]);
            $level++;
        }

        Schema::table('employee_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_categories', function (Blueprint $table) {
            $table->dropUnique(['level']);
            $table->dropColumn('level');
        });
    }
};
