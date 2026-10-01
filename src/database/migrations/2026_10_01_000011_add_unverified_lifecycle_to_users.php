<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('verification_reminder_queued_at')->nullable();
            $table->timestampTz('verification_reminder_sent_at')->nullable();
            $table->timestampTz('verification_warning_queued_at')->nullable();
            $table->timestampTz('verification_warning_sent_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX users_unverified_lifecycle_idx ON users (created_at, id) WHERE email_verified_at IS NULL AND deleted_at IS NULL AND role = 'user' AND status <> 'pending_deletion'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_unverified_lifecycle_idx');
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'verification_reminder_queued_at', 'verification_reminder_sent_at',
            'verification_warning_queued_at', 'verification_warning_sent_at',
        ]));
    }
};
