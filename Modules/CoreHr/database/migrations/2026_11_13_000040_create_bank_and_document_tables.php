<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('code', 3);
            $table->string('name', 150);
            $table->timestamps();

            $table->unique(['bank_id', 'code']);
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_cash')->default(false);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->restrictOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->restrictOnDelete();
            $table->string('account_no', 30)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->timestamps();

            $table->unique(['employment_id', 'effective_from']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('requires_expiry')->default(false);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignId('employment_id')->nullable()->constrained('employments')->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('bank_branches');
        Schema::dropIfExists('banks');
    }
};
