<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('division');       // 1..5
            $table->date('week_start_date');
            $table->string('status', 8)->default('active'); // active|closed
            $table->unsignedSmallInteger('member_count')->default(0);
            $table->timestamps();

            $table->index(['week_start_date', 'division']);
        });

        Schema::create('league_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp_week')->default(0);
            $table->unsignedSmallInteger('rank_final')->nullable();
            $table->string('movement', 6)->nullable();     // up|down|stay
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
            // ერთი მომხმარებელი კვირაში მხოლოდ ერთ ლიგაშია
            $table->index(['user_id', 'league_id']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('icon', 32)->nullable();
            $table->string('tint', 9)->nullable();
            $table->json('name');                          // { ka, ru, en }
            $table->json('description')->nullable();
            $table->json('criteria');                      // { type, exercise_slug, value }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index('following_id');
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('push_token')->nullable();
            $table->string('platform', 8);                 // ios|android
            $table->string('app_version', 16)->nullable();
            $table->string('locale', 5)->default('ka');
            $table->string('device_uuid', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
            $table->index('push_token');
        });

        /** მაქს. 2 push დღეში — ლიმიტი queue-ს დონეზე მოწმდება (სპეც. 15) */
        Schema::create('push_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->date('sent_on');
            $table->timestamp('sent_at');
            $table->boolean('opened')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'sent_on']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 24);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'expires_at']);
        });

        Schema::create('share_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->string('type', 24);                    // session|streak|league|record
            $table->string('url')->nullable();
            $table->string('status', 12)->default('queued'); // queued|ready|failed
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        /**
         * feature_flags — v2-ში შოპი მხოლოდ GE-ში იმუშავებს.
         * თუ ეს v1-ში არ ჩაიდება, კოდბეისი ორად გაიხლიჩება (სპეც. 19.1).
         */
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48);
            $table->string('country_code', 2)->default('*');
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['key', 'country_code']);
        });
    }

    public function down(): void
    {
        foreach ([
            'feature_flags', 'share_cards', 'otp_codes', 'push_log', 'devices',
            'follows', 'user_badges', 'badges', 'league_members', 'leagues',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
