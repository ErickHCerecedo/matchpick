<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wildcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiniela_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team1_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team2_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team3_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedTinyInteger('points_earned')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quiniela_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wildcards');
    }
};
