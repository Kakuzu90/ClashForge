<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the latest processing run claimed the row, so System Health can show the media processing
 * p95 (specs/20 §6, P3-02). The index serves that window read over rows that were processed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->timestampTz('processing_started_at')->nullable()->after('processing_attempts');
        });

        DB::statement('CREATE INDEX media_processed_at_index ON media (processed_at) WHERE processing_started_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS media_processed_at_index');

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('processing_started_at');
        });
    }
};
