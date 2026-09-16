<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 12);                   // yard|park|school|stadium|commercial
            $table->string('access', 12)->default('public'); // public|paid|restricted
            $table->unsignedTinyInteger('condition_rating')->default(3); // 1-5
            $table->boolean('has_lighting')->default(false);
            $table->json('description')->nullable();      // { ka, ru, en }

            $table->string('status', 10)->default('pending'); // pending|verified|rejected
            $table->string('reject_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->unsignedInteger('checkin_count')->default(0);
            $table->decimal('rating_sum', 10, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'city_id']);
        });

        // location — GEOGRAPHY(POINT,4326): მეტრებში ითვლის მანძილს დედამიწის სფეროზე
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE spots ADD COLUMN location GEOGRAPHY(POINT, 4326) NOT NULL');
            DB::statement('CREATE INDEX idx_spots_location ON spots USING GIST (location)');
        } else {
            Schema::table('spots', function (Blueprint $table) {
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
            });
        }

        Schema::create('spot_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->string('equipment_tag', 32);
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->unsignedTinyInteger('condition')->default(3);
            $table->timestamps();

            $table->unique(['spot_id', 'equipment_tag']);
        });

        Schema::create('spot_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('thumb_url')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 10)->default('pending');
            $table->timestamps();

            $table->index(['spot_id', 'status']);
        });

        Schema::create('spot_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->unsignedSmallInteger('distance_m')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['spot_id', 'checked_in_at']);
        });

        Schema::create('spot_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('condition_rating');
            $table->timestamps();

            $table->unique(['spot_id', 'user_id']);
        });

        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('photo_url')->nullable();
            $table->json('bio')->nullable();              // { ka, ru, en }
            $table->string('contact_instagram', 64)->nullable();
            $table->string('contact_phone', 24)->nullable();
            $table->string('contact_telegram', 64)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            // v1-ის ერთადერთი მონეტიზაცია (სპეც. 9.4)
            $table->string('listing_tier', 12)->default('free'); // free|basic|featured
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['city_id', 'listing_tier']);
        });

        Schema::create('trainer_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['trainer_id', 'spot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_spots');
        Schema::dropIfExists('trainers');
        Schema::dropIfExists('spot_ratings');
        Schema::dropIfExists('spot_checkins');
        Schema::dropIfExists('spot_media');
        Schema::dropIfExists('spot_equipment');
        Schema::dropIfExists('spots');
    }
};
