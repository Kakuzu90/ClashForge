<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `users`: an email change waits here until the new address confirms it (FR-AUTH-8).
// Case-insensitive like `email` (citext on Postgres, NOCASE on SQLite).
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::table('users', function (Blueprint $table) use ($pgsql) {
            $column = $table->string('pending_email', 255)->nullable();
            if (! $pgsql) {
                $column->collation('NOCASE');
            }
            $table->timestampTz('pending_email_requested_at')->nullable();
        });

        if ($pgsql) {
            DB::statement('ALTER TABLE users ALTER COLUMN pending_email TYPE citext');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_requested_at']);
        });
    }
};
