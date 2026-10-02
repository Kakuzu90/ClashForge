<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `clans`, the read-only stub (P2-13). The stub fills tag, name, badge and level from the
// player payload; the columns only `/clans/{tag}` returns stay null until the clan sync (P4-01).
// `languages` (platform-supplied, a Postgres array) arrives with recruitment, its only writer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 15);
            $table->string('tag_normalized', 14)->unique();
            $table->string('name', 30);
            $table->text('description')->nullable();
            // Referenced, never mirrored (specs/18 §2.3).
            $table->jsonb('badge_urls')->default('{}');
            $table->unsignedSmallInteger('level')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->unsignedInteger('builder_points')->nullable();
            $table->unsignedInteger('capital_points')->nullable();
            $table->string('war_frequency', 20)->nullable();
            $table->unsignedInteger('war_win_streak')->nullable();
            $table->unsignedInteger('war_wins')->nullable();
            $table->unsignedInteger('war_losses')->nullable();
            $table->unsignedInteger('war_ties')->nullable();
            $table->boolean('is_war_log_public')->nullable();
            $table->unsignedInteger('war_league_id')->nullable();
            $table->string('war_league_name', 50)->nullable();
            $table->unsignedSmallInteger('capital_hall_level')->nullable();
            $table->unsignedSmallInteger('members_count')->nullable();
            $table->unsignedSmallInteger('required_th_level')->nullable();
            $table->unsignedInteger('required_trophies')->nullable();
            $table->string('type', 20)->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('location_name', 50)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestampTz('api_synced_at')->nullable();
            $table->unsignedSmallInteger('api_sync_failures')->default(0);
            $table->string('tracked_reason', 20)->nullable();
            $table->timestampsTz();

            $table->index('members_count');
            $table->index('war_league_id');
            $table->index('country_code');
            $table->index('api_synced_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX clans_name_search_index ON clans USING gin (to_tsvector('simple', name))");
            DB::statement("ALTER TABLE clans ADD CONSTRAINT clans_type_check CHECK (type IN ('open','inviteOnly','closed'))");
            DB::statement("ALTER TABLE clans ADD CONSTRAINT clans_tracked_reason_check CHECK (tracked_reason IN ('member','recruitment','manual'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
