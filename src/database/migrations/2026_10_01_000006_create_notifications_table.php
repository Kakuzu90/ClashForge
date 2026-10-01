<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `notifications`: Laravel's shape plus `group_key`, indexed for the bell. The list index
// matches the centre's order (newest first, id as tie-break) and the unread index is partial; both
// drivers take them as raw DDL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 60);
            $table->string('notifiable_type', 60);
            $table->unsignedBigInteger('notifiable_id');
            $table->jsonb('data')->default('{}');
            $table->timestampTz('read_at')->nullable();
            $table->string('group_key', 100)->nullable();
            $table->timestampsTz();

            $table->index(['group_key', 'notifiable_id']);
        });

        DB::statement('CREATE INDEX notifications_notifiable_latest_index ON notifications (notifiable_type, notifiable_id, created_at DESC, id DESC)');
        DB::statement('CREATE INDEX notifications_unread_index ON notifications (notifiable_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
