<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks which UI user is watching which game usernames
        Schema::create('tracked_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('player_name');
            $table->timestamps();
        });

        // The actual game data imported from JSON
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('player_name')->unique();
            $table->integer('coins')->default(0);
            $table->timestamps();
        });

        Schema::create('player_rods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->index();
            $table->string('name');
            $table->string('icon');
            $table->timestamps();
        });

        Schema::create('player_inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->index();
            $table->boolean('sparkling')->default(false);
            $table->string('name');
            $table->decimal('weight', 8, 2);
            $table->boolean('shiny')->default(false);
            $table->integer('stack')->default(1);
            $table->string('mutation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_inventories');
        Schema::dropIfExists('player_rods');
        Schema::dropIfExists('players');
        Schema::dropIfExists('tracked_players');
    }
};
