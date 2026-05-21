<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiniela_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->integer('exact_scores')->default(0);
            $table->integer('correct_results')->default(0);
            $table->integer('predictions_made')->default(0);
            $table->integer('rank')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['quiniela_id', 'user_id']);
            $table->index(['quiniela_id', 'total_points']);
        });
    }

    public function down(): void { Schema::dropIfExists('standings'); }
};
