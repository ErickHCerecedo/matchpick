<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('venue', 150)->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'finished', 'cancelled'])->default('scheduled');
            $table->timestamp('prediction_closes_at')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamps();
            $table->index('round_id');
            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void { Schema::dropIfExists('matches'); }
};
