<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained()->cascadeOnDelete();
            $table->smallInteger('home_score');
            $table->smallInteger('away_score');
            $table->smallInteger('home_score_penalties')->nullable();
            $table->smallInteger('away_score_penalties')->nullable();
            $table->enum('winner', ['home', 'away', 'draw']);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('match_results'); }
};
