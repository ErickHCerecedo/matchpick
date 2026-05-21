<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiniela_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiniela_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'participant'])->default('participant');
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['quiniela_id', 'user_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('quiniela_user'); }
};
