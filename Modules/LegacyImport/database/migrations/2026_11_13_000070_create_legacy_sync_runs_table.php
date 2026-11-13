<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('from_at');
            $table->dateTime('to_at');
            $table->string('file_path', 255)->default('');
            $table->unsignedInteger('new_hires')->default(0);
            $table->unsignedInteger('exits')->default(0);
            $table->unsignedInteger('changes')->default(0);
            $table->foreignId('applied_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('applied_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_sync_runs');
    }
};
