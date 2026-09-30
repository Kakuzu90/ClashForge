<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `users`: role and account status (specs/04 §1). Enum columns are varchar + CHECK
// (Postgres only; SQLite cannot add constraints to an existing table). Both drivers take the
// partial indexes, so the few staff and sanctioned rows are found without a full scan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user');
            $table->string('status', 20)->default('active');
            $table->string('status_reason', 255)->nullable();
            $table->timestampTz('status_expires_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user','moderator','admin','super_admin'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','restricted','suspended','banned','pending_deletion'))");
        }

        DB::statement("CREATE INDEX users_status_partial_index ON users (status) WHERE status <> 'active'");
        DB::statement("CREATE INDEX users_role_partial_index ON users (role) WHERE role <> 'user'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_status_partial_index');
        DB::statement('DROP INDEX IF EXISTS users_role_partial_index');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'status_reason', 'status_expires_at']);
        });
    }
};
