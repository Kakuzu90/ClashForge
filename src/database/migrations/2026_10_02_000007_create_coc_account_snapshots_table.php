<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_account_snapshots`: written only when a progression value changed (specs/09 §6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coc_account_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coc_account_id')->constrained('coc_accounts')->cascadeOnDelete();
            $table->timestampTz('captured_at');
            $table->unsignedSmallInteger('th_level')->nullable();
            $table->unsignedSmallInteger('builder_hall_level')->nullable();
            $table->unsignedSmallInteger('xp_level')->nullable();
            $table->unsignedInteger('trophies')->nullable();
            $table->unsignedInteger('best_trophies')->nullable();
            $table->unsignedInteger('war_stars')->nullable();
            $table->unsignedInteger('attack_wins')->nullable();
            $table->unsignedInteger('defense_wins')->nullable();
            $table->unsignedInteger('donations')->nullable();
            $table->string('clan_tag', 15)->nullable();
            $table->unsignedInteger('league_id')->nullable();
            $table->jsonb('heroes')->default('[]');
            $table->jsonb('troops')->default('[]');
            $table->jsonb('spells')->default('[]');
            $table->jsonb('hero_equipment')->default('[]');
            $table->string('source', 20);

            $table->unique(['coc_account_id', 'captured_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX coc_account_snapshots_latest_index ON coc_account_snapshots (coc_account_id, captured_at DESC)');
            DB::statement("ALTER TABLE coc_account_snapshots ADD CONSTRAINT coc_account_snapshots_source_check CHECK (source IN ('scheduled','manual','verification'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coc_account_snapshots');
    }
};
