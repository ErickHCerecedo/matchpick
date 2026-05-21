<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiniela_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->smallInteger('home_score');
            $table->smallInteger('away_score');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'quiniela_id', 'match_id']);
            $table->index('quiniela_id');
            $table->index('match_id');
        });
    }

    public function down(): void { Schema::dropIfExists('predictions'); }
};
