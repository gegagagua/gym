<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // chest|back|shoulders|arms|core|legs|pelvic_floor|full_body|mobility
            $table->string('zone', 16)->nullable()->after('category');
            $table->index(['zone', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['zone', 'is_active']);
            $table->dropColumn('zone');
        });
    }
};
