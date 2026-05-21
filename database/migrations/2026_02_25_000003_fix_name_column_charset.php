<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Explicitly alter the name column on both tables to utf8mb4
        // (CONVERT TO doesn't always update indexed columns)
        DB::statement('ALTER TABLE fishes MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE master_rods MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE fishes MODIFY name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE master_rods MODIFY name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
    }
};
