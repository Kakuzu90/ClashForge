<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `audit_logs`: append-only (specs/12 §9). A trigger on both drivers rejects UPDATE and
// DELETE, so not even a raw query edits history; retention later drops monthly partitions, which
// row triggers do not block. `actor_id` restricts deletes: the trigger would refuse a SET NULL anyway.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('action', 60);
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('context')->default('{}');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestampTz('created_at');

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();

                CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs
                    FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_append_only();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_append_only()');
        }
    }
};
