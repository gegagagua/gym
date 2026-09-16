<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('track', 12);                  // home|bar|skills
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('duration_weeks')->default(8);
            $table->unsignedTinyInteger('days_per_week')->default(3);
            $table->json('goals')->nullable();            // რომელ მიზნებს ერგება
            $table->json('equipment')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['track', 'level', 'is_active']);
        });

        Schema::create('program_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'locale']);
        });

        Schema::create('program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_no');
            $table->unsignedTinyInteger('day_no');
            $table->string('title_key', 64)->nullable();
            $table->string('type', 8);                    // workout|rest|test
            $table->unsignedSmallInteger('est_minutes')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'week_no', 'day_no']);
        });

        Schema::create('program_day_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('sets')->default(3);
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->unsignedSmallInteger('target_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->default(90);
            $table->string('tempo', 12)->default('normal'); // normal|slow
            $table->string('notes_key', 64)->nullable();
            $table->timestamps();

            $table->index(['program_day_id', 'sort_order']);
        });

        Schema::create('program_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->date('started_on');
            $table->unsignedTinyInteger('current_week')->default(1);
            $table->unsignedTinyInteger('current_day')->default(1);
            $table->string('status', 12)->default('active');  // active|completed|abandoned
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_enrollments');
        Schema::dropIfExists('program_day_exercises');
        Schema::dropIfExists('program_days');
        Schema::dropIfExists('program_translations');
        Schema::dropIfExists('programs');
    }
};
