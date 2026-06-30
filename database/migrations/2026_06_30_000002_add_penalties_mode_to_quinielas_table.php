<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quinielas', function (Blueprint $table) {
            $table->string('penalties_mode')->nullable()->after('penalties_enabled'); // 'winner' | 'exact'
        });
    }

    public function down(): void
    {
        Schema::table('quinielas', function (Blueprint $table) {
            $table->dropColumn('penalties_mode');
        });
    }
};
