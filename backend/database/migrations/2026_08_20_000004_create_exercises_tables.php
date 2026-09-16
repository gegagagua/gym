<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('category', 32);
            $table->string('force', 8);                 // push|pull|static|legs|core
            $table->string('mechanic', 12)->default('compound');
            $table->string('unit', 8);                  // reps|seconds

            /**
             * XP-ის კოეფიციენტი. საბაზისო: 1 კლასიკური push-up = 1.0 XP.
             * ადმინიდან იმართება; ისტორია არ ზიანდება, რადგან
             * xp_ledger.k_snapshot ინახავს იმ დროს მოქმედ მნიშვნელობას.
             */
            $table->decimal('difficulty_coef', 5, 2);

            $table->unsignedTinyInteger('level_min')->default(1);
            $table->unsignedTinyInteger('level_max')->default(5);
            $table->json('equipment')->nullable();
            $table->json('primary_muscles');
            $table->json('secondary_muscles')->nullable();

            $table->foreignId('progression_from_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->foreignId('progression_to_id')->nullable()->constrained('exercises')->nullOnDelete();

            $table->string('skill_group', 32)->nullable();
            $table->boolean('is_skill_unlock')->default(false);
            $table->unsignedInteger('unlock_bonus_xp')->default(0);
            /** skill-ის ჩათვლის ზღვარი: reps ან წამები (მაგ. handstand 10 წმ) */
            $table->unsignedSmallInteger('unlock_threshold')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['force', 'is_active']);
            $table->index('skill_group');
        });

        Schema::create('exercise_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->string('short_desc', 255)->nullable();
            $table->json('instructions')->nullable();
            $table->json('common_mistakes')->nullable();   // სავალდებულო ბლოკი (სპეც. 18)
            $table->timestamps();

            $table->unique(['exercise_id', 'locale']);
        });

        Schema::create('exercise_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);                   // loop|video|thumbnail|anatomy
            $table->string('url');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('bytes')->nullable();

            // ატრიბუციის ეკრანი ამ სამი ველიდან გენერირდება ავტომატურად (სპეც. 13.1)
            $table->string('license', 24);                // own|cc-by|cc-by-sa|public-domain|licensed
            $table->string('attribution_text')->nullable();
            $table->string('source_url')->nullable();

            $table->timestamps();
            $table->index(['exercise_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_media');
        Schema::dropIfExists('exercise_translations');
        Schema::dropIfExists('exercises');
    }
};
