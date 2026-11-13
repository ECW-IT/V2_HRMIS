<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('all_companies')->default(false)->after('is_active');
            $table->boolean('must_change_password')->default(true)->after('all_companies');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['person_id', 'is_active', 'all_companies', 'must_change_password']);
        });
    }
};
