<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Explicitly alter the name column on both tables to utf8mb4
        // (CONVERT TO doesn't always update indexed columns)
        DB::statement('ALTER TABLE fishes MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE master_rods MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fishes MODIFY name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE master_rods MODIFY name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
    }
};
