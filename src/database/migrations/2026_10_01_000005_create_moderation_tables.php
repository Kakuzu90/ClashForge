<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `moderation_actions` and `user_sanctions` (specs/12 §6). Moderation actions are
// immutable ("no updated_at, no deletes"), enforced by a trigger as for `audit_logs`. `case_id`
// gets its FK with `report_cases` (P3-06). Every user FK restricts: this is the compliance record
// (specs/08 §2, §6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('target_type', 60);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->text('note');
            $table->unsignedInteger('duration_hours')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_hash', 64)->nullable();
            $table->timestampTz('created_at');

            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index('case_id');
        });

        Schema::create('user_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('reason_code', 40);
            $table->string('public_reason', 255);
            $table->text('internal_note');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('lifted_at')->nullable();
            $table->foreignId('moderation_action_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'expires_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX user_sanctions_open_expiry_index ON user_sanctions (expires_at) WHERE expires_at IS NOT NULL AND lifted_at IS NULL');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION moderation_actions_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'moderation_actions is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER moderation_actions_no_update BEFORE UPDATE OR DELETE ON moderation_actions
                    FOR EACH ROW EXECUTE FUNCTION moderation_actions_append_only();

                CREATE TRIGGER moderation_actions_no_truncate BEFORE TRUNCATE ON moderation_actions
                    FOR EACH STATEMENT EXECUTE FUNCTION moderation_actions_append_only();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE INDEX user_sanctions_open_expiry_index ON user_sanctions (expires_at) WHERE expires_at IS NOT NULL AND lifted_at IS NULL');
            DB::unprepared("CREATE TRIGGER moderation_actions_no_update BEFORE UPDATE ON moderation_actions BEGIN SELECT RAISE(ABORT, 'moderation_actions is append-only'); END;");
            DB::unprepared("CREATE TRIGGER moderation_actions_no_delete BEFORE DELETE ON moderation_actions BEGIN SELECT RAISE(ABORT, 'moderation_actions is append-only'); END;");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sanctions');
        Schema::dropIfExists('moderation_actions');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS moderation_actions_append_only()');
        }
    }
};
