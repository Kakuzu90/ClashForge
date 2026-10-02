<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `sync_states`: per-resource sync bookkeeping, owned by CocIntegration (specs/05).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            // Failed retries since the row was frozen; past `coc.sync.frozen_max_attempts` it stops.
            $table->unsignedSmallInteger('frozen_attempts')->default(0);
            // Null: not scheduled (stopped, or the resource is not synced in the background).
            $table->timestampTz('next_due_at')->nullable();
            $table->string('tier', 10);
            $table->timestampsTz();

            $table->unique(['resource_type', 'resource_id']);
            $table->index('last_attempt_at');
        });

        DB::statement('CREATE INDEX sync_states_due_index ON sync_states (next_due_at) WHERE next_due_at IS NOT NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sync_states ADD CONSTRAINT sync_states_resource_type_check CHECK (resource_type IN ('coc_account','clan'))");
            DB::statement("ALTER TABLE sync_states ADD CONSTRAINT sync_states_tier_check CHECK (tier IN ('hot','warm','cold','frozen'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
