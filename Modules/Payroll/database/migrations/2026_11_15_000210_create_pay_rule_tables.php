<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12);
            $table->decimal('percent', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['code', 'effective_from']);
        });

        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedTinyInteger('sequence');
            $table->decimal('width', 12, 2)->nullable();
            $table->decimal('percent', 5, 2);
            $table->timestamps();

            $table->unique(['effective_from', 'sequence']);
        });

        Schema::create('attendance_allowance_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('name', 50);
            $table->decimal('base_amount', 12, 2);
            $table->decimal('per_day_rate', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['code', 'effective_from']);
        });

        Schema::create('grade_pay_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->string('extra_day_method', 10);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['grade_id', 'effective_from']);
        });

        Schema::create('company_pay_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('late_divisor');
            $table->decimal('attendance_min_offset', 3, 1);
            $table->decimal('attendance_max_offset', 3, 1);
            $table->unsignedSmallInteger('hours_per_month');
            $table->unsignedTinyInteger('hours_per_day');
            $table->decimal('ot_multiplier', 3, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_pay_settings');
        Schema::dropIfExists('grade_pay_rules');
        Schema::dropIfExists('attendance_allowance_tiers');
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('statutory_rates');
    }
};
