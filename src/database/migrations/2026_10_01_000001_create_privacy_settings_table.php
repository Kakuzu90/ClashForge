<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `privacy_settings`, one row per user keyed by `user_id` (specs/08 1:1 cascade). The
// visibility CHECK is Postgres only, like the other enum columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('profile_visibility', 10)->default('public');
            $table->boolean('show_coc_accounts')->default(true);
            $table->boolean('show_clan')->default(true);
            $table->boolean('show_activity')->default(true);
            $table->boolean('allow_recruitment_contact')->default(true);
            $table->boolean('allow_marketplace_contact')->default(false);
            $table->boolean('searchable')->default(true);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE privacy_settings ADD CONSTRAINT privacy_settings_visibility_check CHECK (profile_visibility IN ('public','members','private'))");
        }

        // Users created before this migration get the default row now.
        DB::table('users')->orderBy('id')->select('id')->each(function (object $user): void {
            DB::table('privacy_settings')->insertOrIgnore(['user_id' => $user->id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
