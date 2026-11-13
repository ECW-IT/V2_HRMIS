<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('employee_no', 20);
            $table->string('legacy_employee_no', 50)->nullable();
            $table->string('type', 12);
            $table->string('status', 10)->default('ACTIVE');
            $table->date('hire_date');
            $table->date('confirmation_due_date')->nullable();
            $table->date('confirmed_on')->nullable();
            $table->boolean('epf_liable');
            $table->string('epf_no', 20)->nullable();
            $table->date('exit_date')->nullable();
            $table->string('exit_reason', 25)->nullable();
            $table->text('exit_remarks')->nullable();
            $table->foreignId('previous_employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->unsignedBigInteger('active_person_id')
                ->nullable()
                ->storedAs("IF(status <> 'EXITED', person_id, NULL)");
            $table->timestamps();

            $table->unique(['company_id', 'employee_no']);
            $table->unique(['company_id', 'epf_no']);
            $table->unique('active_person_id');
        });

        DB::statement('ALTER TABLE employments ADD CONSTRAINT employments_exit_date_check CHECK (exit_date IS NULL OR exit_date >= hire_date)');
        DB::statement('ALTER TABLE employments ADD CONSTRAINT employments_epf_liable_check CHECK (epf_liable = 0 OR epf_no IS NOT NULL)');
        DB::statement("ALTER TABLE employments ADD CONSTRAINT employments_exited_check CHECK (status <> 'EXITED' OR exit_date IS NOT NULL)");

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('org_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('hr_jobs')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('manager_employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->string('change_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['employment_id', 'effective_from']);
        });

        Schema::create('employment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->string('type', 30);
            $table->date('event_date');
            $table->json('details')->nullable();
            $table->foreignId('related_employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employment_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_events');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('employments');
    }
};
