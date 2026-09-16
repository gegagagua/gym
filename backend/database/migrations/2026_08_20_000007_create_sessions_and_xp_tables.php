<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spot_checkin_id')->nullable()->constrained('spot_checkins')->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('source', 12);                 // program|freestyle|test

            // T0..T3 (სპეც. 8)
            $table->unsignedTinyInteger('verification_tier')->default(1);
            $table->string('status', 12)->default('completed'); // completed|flagged|rejected
            $table->json('flags')->nullable();

            $table->unsignedInteger('total_xp')->default(0);
            $table->unsignedInteger('total_reps')->default(0);
            $table->unsignedInteger('total_seconds')->default(0);

            // Apple Health / Health Connect v1.1-ისთვის მზადება (სპეც. 4.2)
            $table->string('external_sync_id', 64)->nullable();
            $table->unsignedSmallInteger('calories_estimated')->nullable();

            $table->integer('device_clock_offset_ms')->default(0);
            $table->uuid('client_uuid');                   // იდემპოტენტურობის გასაღები
            $table->timestamps();

            $table->unique('client_uuid', 'idx_sessions_client_uuid');
            $table->index(['user_id', 'started_at'], 'idx_sessions_user_time');
            $table->index(['user_id', 'status']);
        });

        Schema::create('session_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('set_no');
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->decimal('added_weight_kg', 5, 2)->default(0);
            $table->string('tempo', 12)->default('normal');
            $table->unsignedInteger('rest_after_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('session_id', 'idx_sets_session');
        });

        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->string('metric', 12);                 // max_reps|max_seconds|max_weight
            $table->decimal('value', 8, 2);
            $table->foreignId('session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->timestamp('achieved_at');
            $table->unsignedTinyInteger('verification_tier')->default(1);
            $table->string('video_url')->nullable();       // T3-ისთვის
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id', 'metric']);
            $table->index(['exercise_id', 'metric', 'value']);
        });

        /**
         * xp_ledger — APPEND ONLY.
         * არასდროს არ განახლდება და არ იშლება. მომხმარებლის ჯამური XP
         * არსად არ ინახება ცვალებად ველად — ის აგრეგირდება აქედან და
         * ქეშირდება Redis-ში. სპორული სესია უკუიგდება `adjustment`
         * ჩანაწერით, და არა წაშლით (სპეც. 10.3).
         */
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 24);                 // workout|skill_unlock|spot_added|referral|adjustment
            $table->decimal('raw_value', 10, 2)->nullable();
            $table->decimal('k_snapshot', 5, 2)->nullable();
            $table->json('multipliers')->nullable();
            $table->integer('xp');                        // adjustment-ისთვის უარყოფითიც შეიძლება
            // ლიგაში ჩასათვლელი ნაწილი — T0 სესიები ლიგაში ლიმიტირებულია
            $table->integer('league_xp')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'occurred_at'], 'idx_xp_ledger_user_time');
            $table->index(['user_id', 'reason']);
        });

        /** v2-ის ფასდაკლების ვალუტისთვის — ბალანსი არასდროს არ არის ველი (სპეც. 19.2) */
        Schema::create('xp_spent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 24);                 // order_discount|expiry|manual
            $table->unsignedInteger('xp');
            $table->string('reference', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('streaks', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_days')->default(0);
            $table->unsignedSmallInteger('longest_days')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->unsignedTinyInteger('freeze_tokens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('xp_spent');
        Schema::dropIfExists('xp_ledger');
        Schema::dropIfExists('personal_records');
        Schema::dropIfExists('session_sets');
        Schema::dropIfExists('workout_sessions');
    }
};
