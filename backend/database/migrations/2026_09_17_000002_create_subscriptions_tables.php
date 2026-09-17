<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * ერთი რიგი = ერთი entitlement ერთ პროვაიდერზე. სტატუსს webhook
         * ანახლებს; წვდომა `expires_at`-იდან ითვლება, არა სტატუსიდან —
         * გაუქმებული გამოწერა ვადის ბოლომდე ისევ მოქმედია.
         */
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);               // revenuecat|manual
            $table->string('entitlement', 32)->default('premium');
            $table->string('status', 16);                 // active|cancelled|billing_issue|expired
            $table->string('product_id', 128)->nullable();
            $table->string('store', 24)->nullable();      // app_store|play_store|promotional
            $table->string('period_type', 16)->nullable(); // normal|trial|intro
            $table->string('environment', 12)->nullable(); // PRODUCTION|SANDBOX
            $table->timestamp('expires_at')->nullable();  // null = ვადის გარეშე (manual)
            $table->unsignedBigInteger('last_event_ms')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'entitlement']);
            $table->index(['entitlement', 'expires_at']);
        });

        // webhook-ის იდემპოტენტურობა: RevenueCat ხელახლა აგზავნის იმავე event.id-ს
        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 128);
            $table->string('type', 48);
            $table->string('app_user_id', 128)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('subscriptions');
    }
};
