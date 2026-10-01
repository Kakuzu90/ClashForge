<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/07 `sessions`: the columns the session list shows (FR-AUTH-7). The raw IP goes; only its
// HMAC is kept (specs/11 §5). `country_code` comes from the CDN's country header.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable();
            $table->string('device_label', 100)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['ip_hash', 'device_label', 'country_code', 'created_at']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
        });
    }
};
