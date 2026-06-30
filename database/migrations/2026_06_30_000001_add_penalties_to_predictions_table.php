<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->string('penalties_winner')->nullable()->after('away_score'); // 'home' | 'away'
            $table->smallInteger('penalties_home')->nullable()->after('penalties_winner');
            $table->smallInteger('penalties_away')->nullable()->after('penalties_home');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['penalties_winner', 'penalties_home', 'penalties_away']);
        });
    }
};
