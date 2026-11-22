<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('devices')) {
            $groups = DB::table('devices')
                ->whereNotNull('device_group')
                ->where('device_group', '!=', '')
                ->distinct()
                ->orderBy('device_group')
                ->pluck('device_group');

            $now = now();
            foreach ($groups as $code) {
                DB::table('device_groups')->insert([
                    'code' => $code,
                    'name' => $code,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_groups');
    }
};
