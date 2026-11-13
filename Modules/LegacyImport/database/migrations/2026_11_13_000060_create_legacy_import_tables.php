<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('stage', 10);
            $table->json('summary')->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('stg_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->unsignedInteger('legacy_hrm_id');
            $table->integer('legacy_branch_id')->nullable();
            $table->json('raw');
            $table->string('normalised_employee_no', 30)->nullable();
            $table->string('normalised_nic', 12)->nullable();
            $table->string('company_code', 10)->nullable();
            $table->unsignedBigInteger('loaded_employment_id')->nullable();
            $table->text('load_error')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'legacy_hrm_id']);
        });

        Schema::create('stg_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->unsignedInteger('legacy_hrm_id')->nullable();
            $table->string('code', 10);
            $table->string('severity', 7);
            $table->string('message', 500);
            $table->timestamps();

            $table->index(['import_run_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stg_issues');
        Schema::dropIfExists('stg_employees');
        Schema::dropIfExists('import_runs');
    }
};
