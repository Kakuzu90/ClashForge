<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 "Media". Enum columns are varchar + CHECK (Postgres only; SQLite cannot add constraints).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('attachable_type', 60)->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->string('collection', 30);
            $table->string('kind', 10);
            $table->string('disk', 20);
            $table->text('path');
            $table->string('original_filename', 255);
            // Detected from the file signature during processing; null until then.
            $table->string('mime_type', 100)->nullable();
            $table->string('extension', 10)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 6, 2)->nullable();
            $table->char('checksum_sha256', 64)->nullable()->index();
            $table->string('status', 20);
            $table->string('failure_reason', 100)->nullable();
            $table->string('visibility', 10);
            $table->smallInteger('position')->default(0);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['disk', 'path']);
            $table->index(['attachable_type', 'attachable_id', 'collection', 'position']);
        });

        // Valid on both SQLite and Postgres.
        DB::statement('CREATE INDEX media_user_id_created_at_index ON media (user_id, created_at DESC)');
        // The orphan sweeper's index: unattached rows by expiry.
        DB::statement('CREATE INDEX media_unattached_expires_at_index ON media (expires_at) WHERE attachable_id IS NULL');

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('variant', 20);
            $table->text('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 100);
            $table->timestampTz('created_at')->nullable();

            $table->unique(['media_id', 'variant']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media ADD CONSTRAINT media_status_check CHECK (status IN ('pending','uploaded','processing','ready','failed','quarantined','deleting'))");
            DB::statement("ALTER TABLE media ADD CONSTRAINT media_collection_check CHECK (collection IN ('avatar','account_image','base_screenshot','base_video','evidence','portfolio'))");
            DB::statement("ALTER TABLE media ADD CONSTRAINT media_kind_check CHECK (kind IN ('image','video'))");
            DB::statement("ALTER TABLE media ADD CONSTRAINT media_visibility_check CHECK (visibility IN ('public','private'))");
            DB::statement("ALTER TABLE media_variants ADD CONSTRAINT media_variants_variant_check CHECK (variant IN ('thumb','card','full','poster','video_720p'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
    }
};
