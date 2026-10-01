<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::table('users', function (Blueprint $table) use ($pgsql): void {
            $table->string('password')->nullable()->change();
            if (! $pgsql) {
                $table->string('username', 39)->collation('NOCASE')->change();
            }
            $table->timestampTz('deletion_requested_at')->nullable();
            $table->string('deletion_previous_status', 20)->nullable();
        });

        DB::statement('CREATE INDEX users_deletion_requested_partial_index ON users (deletion_requested_at) WHERE deletion_requested_at IS NOT NULL');
        if ($pgsql) {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_shape_check CHECK (username ~ '^[a-z0-9_]{3,20}$' OR (deleted_at IS NOT NULL AND username = 'deleted_user_' || lower(ulid)))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_password_tombstone_check CHECK (password IS NOT NULL OR (deleted_at IS NOT NULL AND status = 'banned'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_deletion_previous_status_check CHECK (deletion_previous_status IN ('active', 'restricted'))");
        }

        Schema::create('username_history', function (Blueprint $table) use ($pgsql): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $column = $table->string('username', 20);
            if (! $pgsql) {
                $column->collation('NOCASE');
            }
            $table->timestampTz('released_at');
            $table->boolean('reserved_forever')->default(false);
            $table->timestampsTz();
            $table->unique(['username', 'released_at']);
            $table->index('username');
        });

        if ($pgsql) {
            DB::statement('ALTER TABLE username_history ALTER COLUMN username TYPE citext');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('username_history');
        DB::statement('DROP INDEX IF EXISTS users_deletion_requested_partial_index');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_shape_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_password_tombstone_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_deletion_previous_status_check');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deletion_requested_at', 'deletion_previous_status']);
        });
    }
};
