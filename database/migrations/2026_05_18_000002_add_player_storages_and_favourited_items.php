<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_inventories', function (Blueprint $table) {
            $table->boolean('favourited')->default(false)->after('mutation');
        });

        Schema::create('player_storages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->index();
            $table->boolean('sparkling')->default(false);
            $table->string('name');
            $table->double('weight');
            $table->boolean('shiny')->default(false);
            $table->integer('stack')->default(1);
            $table->string('mutation')->nullable();
            $table->boolean('favourited')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_storages');

        Schema::table('player_inventories', function (Blueprint $table) {
            $table->dropColumn('favourited');
        });
    }
};
