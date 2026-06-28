<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quinielas', function (Blueprint $table) {
            $table->boolean('penalties_enabled')->default(false)->after('wildcard_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('quinielas', function (Blueprint $table) {
            $table->dropColumn('penalties_enabled');
        });
    }
};
