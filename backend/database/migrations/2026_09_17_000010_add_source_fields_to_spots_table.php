<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * მოედნის წარმომავლობა და საკონტაქტო ველები.
 *
 * source: seed | ugc | osm | web. external_id — გარე წყაროს სტაბილური
 * გასაღები (`osm:node/123`, `web:…`), იმპორტის idempotency მისზეა.
 * null = ძველი/UGC რიგი — unique ინდექსი null-ებს Postgres-ში არ ადარებს.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->string('source', 8)->nullable()->after('status');
            $table->string('external_id', 64)->nullable()->unique()->after('source');
            $table->string('address')->nullable()->after('description');
            $table->string('phone', 64)->nullable()->after('address');
            $table->string('website')->nullable()->after('phone');
            $table->string('opening_hours')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['source', 'external_id', 'address', 'phone', 'website', 'opening_hours']);
        });
    }
};
