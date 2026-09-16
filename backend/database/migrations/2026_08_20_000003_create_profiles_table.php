<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('gender', 8)->nullable();          // male | female | other
            $table->unsignedSmallInteger('height_cm')->nullable();
            // წონა XP-ის weight_mod-ისთვისაა საჭირო — საჯაროდ არასდროს ჩანს (სპეც. 17)
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('goal', 16)->nullable();           // strength|muscle|weight_loss|skills|health
            $table->json('equipment')->nullable();            // ["none","bar","yard","gym"]
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(true);
            // დონის ტესტის ნედლი შედეგები — გადათვლისთვის ვინახავთ
            $table->json('level_test')->nullable();           // { pushup, pullup, plank_sec, taken_at }
            $table->timestamp('disclaimer_accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
