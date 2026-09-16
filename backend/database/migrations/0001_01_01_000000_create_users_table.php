<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ავტორიზაციის იდენტიფიკატორები — ყველა nullable,
            // რადგან სტუმარს არც ერთი არ აქვს.
            $table->string('phone', 24)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('provider', 16)->nullable();     // google | apple | phone | guest
            $table->string('provider_id')->nullable();
            $table->string('device_uuid', 64)->nullable()->unique(); // სტუმრის იდენტობა
            $table->string('password')->nullable();          // მხოლოდ ადმინებისთვის

            $table->string('username', 32)->nullable()->unique();
            $table->string('display_name', 64)->nullable();
            $table->string('avatar_url')->nullable();

            $table->string('locale', 5)->default('ka');
            $table->string('timezone', 48)->default('Asia/Tbilisi');
            $table->string('country_code', 2)->default('GE');

            $table->boolean('is_admin')->default(false);
            $table->boolean('is_moderator')->default(false);
            // 16 წლამდე — ლიდერბორდი და სოციალური ფუნქციები ითიშება (სპეც. 17)
            $table->boolean('social_enabled')->default(true);

            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // 30-დღიანი grace წაშლამდე (Apple-ის მოთხოვნა)
            $table->softDeletes();
            $table->timestamp('purge_after')->nullable();

            $table->unique(['provider', 'provider_id']);
            $table->index('last_active_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
