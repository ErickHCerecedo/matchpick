<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['group', 'round_of_32', 'round_of_16', 'quarter', 'semi', 'third_place', 'final']);
            $table->smallInteger('order');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->index(['tournament_id', 'order']);
        });
    }

    public function down(): void { Schema::dropIfExists('rounds'); }
};
