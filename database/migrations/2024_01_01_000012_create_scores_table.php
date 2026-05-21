<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_result_id')->constrained('match_results')->cascadeOnDelete();
            $table->smallInteger('points')->default(0);
            $table->jsonb('breakdown')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->unique(['prediction_id', 'match_result_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('scores'); }
};
