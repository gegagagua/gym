<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // კალენდარის პლანერი (premium): დღეები × ლოკაცია × ინტენსიობა → გენერირებული განრიგი
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('active');   // active|archived
            $table->string('intensity', 12);                    // light|moderate|intense
            $table->unsignedTinyInteger('level');
            $table->string('goal', 16)->nullable();
            $table->json('schedule');                           // [{weekday: 1-7, location: home|yard|gym}]
            $table->unsignedTinyInteger('weeks');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('training_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('week_no');
            $table->unsignedTinyInteger('weekday');
            $table->string('type', 8);                          // workout|rest
            $table->string('split', 8)->nullable();             // full|upper|lower|push|pull|legs
            $table->string('location', 8)->nullable();
            $table->json('focus')->nullable();                  // ზონები
            $table->unsignedSmallInteger('est_minutes')->nullable();
            $table->boolean('is_deload')->default(false);
            $table->foreignId('session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_plan_id', 'date']);
        });

        Schema::create('training_plan_day_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('sets');
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->unsignedSmallInteger('target_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds');
            $table->timestamps();

            $table->index(['training_plan_day_id', 'sort_order']);
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->foreignId('plan_day_id')->nullable()->after('program_day_id')
                ->constrained('training_plan_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_day_id');
        });
        Schema::dropIfExists('training_plan_day_exercises');
        Schema::dropIfExists('training_plan_days');
        Schema::dropIfExists('training_plans');
    }
};
