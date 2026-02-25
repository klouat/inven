<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fishes', function (Blueprint $table) {
            $table->string('rarity')->nullable()->after('max_weight');
            $table->string('icon')->nullable()->after('rarity');
            $table->string('from')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('fishes', function (Blueprint $table) {
            $table->dropColumn(['rarity', 'icon', 'from']);
        });
    }
};
