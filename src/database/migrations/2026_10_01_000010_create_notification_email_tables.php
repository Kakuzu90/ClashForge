<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->jsonb('channel_prefs')->default('{}');
            $table->boolean('non_security_email_enabled')->default(true);
            $table->string('digest_frequency', 10)->default('none');
            $table->timestampTz('updated_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_digest_check CHECK (digest_frequency IN ('none', 'daily', 'weekly'))");
        }

        Schema::create('notification_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('event_key', 100);
            $table->timestampTz('sent_at');
            $table->unique(['user_id', 'type', 'event_key'], 'notification_email_event_unique');
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_deliveries');
        Schema::dropIfExists('notification_preferences');
    }
};
