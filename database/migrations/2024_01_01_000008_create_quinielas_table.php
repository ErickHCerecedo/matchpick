<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quinielas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug')->unique()->index();
            $table->text('description')->nullable();
            $table->enum('type', ['public', 'private'])->default('private');
            $table->enum('scoring_type', ['standard'])->default('standard');
            $table->integer('max_participants')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('predictions_open')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('creator_id');
            $table->index('tournament_id');
            $table->index('type');
        });
    }

    public function down(): void { Schema::dropIfExists('quinielas'); }
};
