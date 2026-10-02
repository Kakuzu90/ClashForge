<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_accounts` and `coc_account_claims`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coc_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tag', 15);
            $table->string('tag_normalized', 14);
            $table->string('status', 20);
            $table->timestampTz('verified_at')->nullable();
            $table->string('verification_method', 20)->nullable();
            $table->string('ign', 30);
            // Game stats are nullable: a field the API stops sending reads as null (specs/23 §5).
            $table->unsignedSmallInteger('th_level')->nullable();
            $table->unsignedSmallInteger('builder_hall_level')->nullable();
            $table->unsignedSmallInteger('xp_level')->nullable();
            $table->unsignedInteger('trophies')->nullable();
            $table->unsignedInteger('best_trophies')->nullable();
            $table->unsignedInteger('builder_trophies')->nullable();
            $table->unsignedInteger('war_stars')->nullable();
            $table->unsignedInteger('attack_wins')->nullable();
            $table->unsignedInteger('defense_wins')->nullable();
            $table->unsignedInteger('donations')->nullable();
            $table->unsignedInteger('donations_received')->nullable();
            // `clan_id` arrives with the clans stub (P2-13).
            $table->string('clan_tag', 15)->nullable();
            $table->string('clan_role', 20)->nullable();
            $table->unsignedInteger('league_id')->nullable();
            $table->string('league_name', 50)->nullable();
            $table->text('league_icon_url')->nullable();
            $table->jsonb('troops')->default('[]');
            $table->jsonb('heroes')->default('[]');
            $table->jsonb('spells')->default('[]');
            $table->jsonb('hero_equipment')->default('[]');
            $table->jsonb('achievements')->default('[]');
            $table->jsonb('labels')->default('[]');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampTz('api_synced_at')->nullable();
            $table->unsignedSmallInteger('api_sync_failures')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('images_count')->default(0);
            // No `deleted_at`: ownership history is never deleted (specs/13 §1).
            $table->timestampsTz();

            $table->unique(['user_id', 'tag_normalized']);
            $table->index(['user_id', 'status']);
            $table->index('tag_normalized');
            $table->index('api_synced_at');
        });

        Schema::create('coc_account_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coc_account_id')->nullable()->constrained('coc_accounts')->nullOnDelete();
            $table->string('tag_normalized', 14);
            // Claims are forensic history: a user with claims cannot be hard-deleted (specs/07).
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('method', 20);
            $table->string('status', 20);
            $table->string('failure_reason', 100)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at');

            $table->index(['tag_normalized', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // The platform's core rule: one holder per tag (specs/13 §1). A `disputed` row still holds
        // the tag (specs/13 §2), so the index covers it too.
        DB::statement("CREATE UNIQUE INDEX coc_accounts_one_verified_owner ON coc_accounts (tag_normalized) WHERE status IN ('verified', 'disputed')");
        DB::statement('CREATE UNIQUE INDEX coc_accounts_one_featured ON coc_accounts (user_id) WHERE is_featured');
        DB::statement("CREATE INDEX coc_account_claims_pending_index ON coc_account_claims (status) WHERE status = 'pending'");

        // Enum columns are varchar + CHECK, Postgres only: SQLite cannot add constraints.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX coc_accounts_th_trophies_index ON coc_accounts (th_level, trophies DESC)');
            DB::statement("CREATE INDEX coc_accounts_ign_search_index ON coc_accounts USING gin (to_tsvector('simple', ign))");
            DB::statement("ALTER TABLE coc_accounts ADD CONSTRAINT coc_accounts_status_check CHECK (status IN ('unverified','verified','disputed','suspended','released'))");
            DB::statement("ALTER TABLE coc_accounts ADD CONSTRAINT coc_accounts_verification_method_check CHECK (verification_method IN ('api_token','admin'))");
            DB::statement('ALTER TABLE coc_accounts ADD CONSTRAINT coc_accounts_th_level_check CHECK (th_level BETWEEN 1 AND 30)');
            DB::statement('ALTER TABLE coc_accounts ADD CONSTRAINT coc_accounts_images_count_check CHECK (images_count <= 5)');
            DB::statement("ALTER TABLE coc_account_claims ADD CONSTRAINT coc_account_claims_method_check CHECK (method IN ('api_token','dispute','admin'))");
            DB::statement("ALTER TABLE coc_account_claims ADD CONSTRAINT coc_account_claims_status_check CHECK (status IN ('pending','succeeded','failed','rejected','superseded'))");
        } else {
            DB::statement('CREATE INDEX coc_accounts_th_trophies_index ON coc_accounts (th_level, trophies)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coc_account_claims');
        Schema::dropIfExists('coc_accounts');
    }
};
