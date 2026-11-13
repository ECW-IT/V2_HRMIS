<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_definition_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_definition_id')->constrained('approval_definitions')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('approver_type', 10);
            $table->string('approver_ref', 100)->nullable();
            $table->timestamps();

            $table->unique(['approval_definition_id', 'sequence']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_definition_id')->constrained('approval_definitions')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('subject_type', 150);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 10);
            $table->json('payload');
            $table->string('status', 10)->default('PENDING');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'company_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 10);
            $table->text('remarks')->nullable();
            $table->dateTime('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_definition_steps');
        Schema::dropIfExists('approval_definitions');
    }
};
