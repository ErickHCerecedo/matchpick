<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Display text shown when home_team_id / away_team_id is null.
            // e.g. "1ro Grupo A", "Mejor 3ro (A/B/C)", "Gan. 32avos #3"
            $table->string('home_placeholder', 60)->nullable()->after('home_team_id');
            $table->string('away_placeholder', 60)->nullable()->after('away_team_id');

            // Visual position in the knockout bracket tree (1-indexed per round).
            // Enables correct ordering for bracket rendering without coupling
            // to scheduled_at (which follows FIFA scheduling, not bracket order).
            $table->smallInteger('bracket_slot')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['home_placeholder', 'away_placeholder', 'bracket_slot']);
        });
    }
};
