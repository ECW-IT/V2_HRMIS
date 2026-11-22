<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });

        Schema::rename('hr_jobs', 'designations');

        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('job_id', 'designation_id');
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->renameColumn('job_id', 'designation_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreign('designation_id')->references('id')->on('designations')->restrictOnDelete();
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->foreign('designation_id')->references('id')->on('designations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['designation_id']);
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->dropForeign(['designation_id']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('designation_id', 'job_id');
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->renameColumn('designation_id', 'job_id');
        });

        Schema::rename('designations', 'hr_jobs');

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('hr_jobs')->restrictOnDelete();
        });

        Schema::table('pay_run_employees', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('hr_jobs')->restrictOnDelete();
        });
    }
};
