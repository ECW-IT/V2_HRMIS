<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legacy_sync_runs')->delete();

        Schema::table('legacy_sync_runs', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->after('id')
                ->constrained('companies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legacy_sync_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
