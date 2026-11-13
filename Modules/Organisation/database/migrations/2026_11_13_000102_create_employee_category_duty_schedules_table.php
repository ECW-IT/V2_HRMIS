<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_category_duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained('employee_categories')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->time('on_duty_time')->nullable();
            $table->time('off_duty_time')->nullable();
            $table->time('morning_short_beginning')->nullable();
            $table->time('morning_short_ending')->nullable();
            $table->time('evening_short_beginning')->nullable();
            $table->time('evening_short_ending')->nullable();
            $table->time('halfday_beginning')->nullable();
            $table->time('late_on')->nullable();
            $table->timestamps();

            $table->unique(['employee_category_id', 'effective_from'], 'emp_cat_duty_cat_from_unique');
            $table->index(['employee_category_id', 'effective_from', 'effective_to'], 'emp_cat_duty_range_index');
        });

        $categories = DB::table('employee_categories')->get([
            'id',
            'on_duty_time',
            'off_duty_time',
            'morning_short_beginning',
            'morning_short_ending',
            'evening_short_beginning',
            'evening_short_ending',
            'halfday_beginning',
            'late_on',
        ]);

        $now = now();
        foreach ($categories as $category) {
            DB::table('employee_category_duty_schedules')->insert([
                'employee_category_id' => $category->id,
                'effective_from' => '2000-01-01',
                'effective_to' => null,
                'on_duty_time' => $category->on_duty_time,
                'off_duty_time' => $category->off_duty_time,
                'morning_short_beginning' => $category->morning_short_beginning,
                'morning_short_ending' => $category->morning_short_ending,
                'evening_short_beginning' => $category->evening_short_beginning,
                'evening_short_ending' => $category->evening_short_ending,
                'halfday_beginning' => $category->halfday_beginning,
                'late_on' => $category->late_on,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_category_duty_schedules');
    }
};
