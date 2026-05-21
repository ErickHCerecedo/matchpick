<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('iso_code', 3)->unique();
            $table->char('iso2_code', 2)->nullable();
            $table->string('flag_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('countries'); }
};
