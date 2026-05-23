<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->boolean('is_custom')->default(false)->after('is_active');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('tournament_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Add 'general' to rounds type check constraint (PostgreSQL)
        DB::statement("ALTER TABLE rounds DROP CONSTRAINT IF EXISTS rounds_type_check");
        DB::statement("ALTER TABLE rounds ADD CONSTRAINT rounds_type_check CHECK (type::text = ANY (ARRAY['group','round_of_32','round_of_16','quarter','semi','third_place','final','general']::text[]))");
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Tournament::class);
            $table->dropColumn('tournament_id');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'creator_id');
            $table->dropColumn(['creator_id', 'is_custom']);
        });

        DB::statement("ALTER TABLE rounds DROP CONSTRAINT IF EXISTS rounds_type_check");
        DB::statement("ALTER TABLE rounds ADD CONSTRAINT rounds_type_check CHECK (type::text = ANY (ARRAY['group','round_of_32','round_of_16','quarter','semi','third_place','final']::text[]))");
    }
};
