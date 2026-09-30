<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/07 "Media": the retry counter for media:retry-failed and the reconcile job's first-pass
// state (specs/10 §9).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->smallInteger('processing_attempts')->default(0);
        });

        Schema::create('media_storage_orphans', function (Blueprint $table) {
            $table->text('path')->primary();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_storage_orphans');

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('processing_attempts');
        });
    }
};
