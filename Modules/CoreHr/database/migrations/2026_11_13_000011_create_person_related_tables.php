<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type', 12);
            $table->string('value', 150);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('person_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('line1', 150);
            $table->string('line2', 150)->nullable();
            $table->string('city', 80);
            $table->string('district', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'type']);
        });

        Schema::create('person_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 50);
            $table->string('phone', 20);
            $table->string('address', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('person_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 50);
            $table->date('date_of_birth')->nullable();
            $table->timestamps();
        });

        Schema::create('person_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('institute', 150);
            $table->string('title', 150);
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();
        });

        Schema::create('person_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('employer', 150);
            $table->string('job_title', 150);
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_experience');
        Schema::dropIfExists('person_qualifications');
        Schema::dropIfExists('person_dependents');
        Schema::dropIfExists('person_emergency_contacts');
        Schema::dropIfExists('person_addresses');
        Schema::dropIfExists('person_contacts');
    }
};
