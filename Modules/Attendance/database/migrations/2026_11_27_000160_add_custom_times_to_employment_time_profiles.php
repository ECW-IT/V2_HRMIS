<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_time_profiles', function (Blueprint $table) {
            $table->boolean('uses_custom_times')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('late_from')->nullable();
            $table->time('morning_cutoff')->nullable();
            $table->time('half_day_from')->nullable();
            $table->time('early_leave_from')->nullable();
        });

        Schema::create('employment_time_profile_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_time_profile_id');
            $table->foreign('employment_time_profile_id', 'etp_breaks_profile_fk')
                ->references('id')
                ->on('employment_time_profiles')
                ->cascadeOnDelete();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_time_profile_breaks');

        Schema::table('employment_time_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'uses_custom_times',
                'start_time',
                'end_time',
                'late_from',
                'morning_cutoff',
                'half_day_from',
                'early_leave_from',
            ]);
        });
    }
};
