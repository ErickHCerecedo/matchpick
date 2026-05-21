<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->unique()->index();
            $table->enum('type', ['world_cup', 'league', 'cup'])->default('world_cup');
            $table->string('season', 10);
            $table->string('logo_url')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('tournaments'); }
};
