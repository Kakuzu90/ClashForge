<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// specs/07 `users`, the columns P1-01 needs. Role/status arrive with P1-02, profile data (and the
// display name) with P1-03. Username and email are case-insensitive: citext on Postgres,
// NOCASE collation on SQLite.
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        if ($pgsql) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->char('ulid', 26)->nullable()->after('id');
            $table->string('username', 20)->nullable()->after('ulid');
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip_hash', 64)->nullable();
            $table->softDeletesTz();
        });

        // Rows created before this migration (the local dev sign-in user) get derived values.
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'ulid' => Str::lower((string) Str::ulid()),
                'username' => 'user_'.$user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        if ($pgsql) {
            DB::statement('ALTER TABLE users ALTER COLUMN ulid SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN username TYPE citext, ALTER COLUMN username SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
            // The scaffold created these as `timestamp`; specs/07 stores every time with its zone.
            DB::statement('ALTER TABLE users ALTER COLUMN email_verified_at TYPE timestamptz, ALTER COLUMN created_at TYPE timestamptz, ALTER COLUMN updated_at TYPE timestamptz');
            DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE timestamptz');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->char('ulid', 26)->nullable(false)->change();
                $table->string('username', 20)->collation('NOCASE')->nullable(false)->change();
                $table->string('email')->collation('NOCASE')->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('ulid');
            $table->unique('username');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE timestamp');
            DB::statement('ALTER TABLE users ALTER COLUMN email_verified_at TYPE timestamp, ALTER COLUMN created_at TYPE timestamp, ALTER COLUMN updated_at TYPE timestamp');
            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE varchar(255)');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropUnique(['username']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['ulid', 'username', 'last_login_at', 'last_login_ip_hash', 'deleted_at']);
            $table->string('name')->default('');
        });
    }
};
