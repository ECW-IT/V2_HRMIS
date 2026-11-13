<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('nic', 12)->nullable()->unique();
            $table->string('nic_as_entered', 12)->nullable();
            $table->string('passport_no', 20)->nullable()->unique();
            $table->string('title', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('name_with_initials', 150);
            $table->string('calling_name', 50)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 6);
            $table->string('marital_status', 10)->nullable();
            $table->string('nationality', 50)->default('Sri Lankan');
            $table->string('photo_path', 255)->nullable();
            $table->boolean('nic_mismatch')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE persons ADD CONSTRAINT persons_nic_or_passport_check CHECK (nic IS NOT NULL OR passport_no IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
