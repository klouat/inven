<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_rods', function (Blueprint $table) {
            $table->text('description')->nullable()->after('image_url');
            $table->string('hint')->nullable()->after('description');
            $table->string('from')->nullable()->after('hint');
            $table->string('strength')->nullable()->after('from');      // string — can be "inf"
            $table->string('line_distance')->nullable()->after('strength'); // can also be "inf"
            $table->float('luck')->default(0)->after('line_distance');
            $table->float('lure_speed')->default(0)->after('luck');
            $table->float('resilience')->default(0)->after('lure_speed');
            $table->float('control')->default(0)->after('resilience');
            $table->integer('level_requirement')->default(0)->after('control');
            $table->integer('disturbance')->nullable()->after('level_requirement');
            $table->json('mutation_pool')->nullable()->after('disturbance');
            $table->json('preferred_disturbance')->nullable()->after('mutation_pool');
        });
    }

    public function down(): void
    {
        Schema::table('master_rods', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'hint', 'from', 'strength', 'line_distance',
                'luck', 'lure_speed', 'resilience', 'control',
                'level_requirement', 'disturbance', 'mutation_pool', 'preferred_disturbance',
            ]);
        });
    }
};
