<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('short_name', 10)->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('logo_url')->nullable();
            $table->boolean('is_national_team')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('teams'); }
};
