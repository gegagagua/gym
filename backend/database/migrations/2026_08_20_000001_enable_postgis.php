<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }
    }

    public function down(): void
    {
        // ექსტენშენს არ ვშლით — სხვა სქემებმაც შეიძლება გამოიყენონ.
    }
};
