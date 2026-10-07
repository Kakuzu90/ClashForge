<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 Bases: `base_layouts`, `base_metrics`, `base_tags`, `base_layout_tag` (P3-01). The
// `search_vector` column and its GIN index join with Search v1 (P3-05).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_layouts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug', 90)->unique();
            // Author deletion removes their bases (specs/08 §6).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coc_account_id')->nullable()->constrained('coc_accounts')->nullOnDelete();
            $table->string('title', 80);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('th_level');
            $table->string('category', 20);
            $table->text('base_link');
            $table->string('layout_hash', 64);
            $table->string('visibility', 10);
            $table->string('status', 20);
            $table->boolean('has_video')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->string('moderation_state', 20)->default('clean');
            $table->string('flagged_reason', 50)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('layout_hash');
            $table->index('coc_account_id');
        });

        Schema::create('base_metrics', function (Blueprint $table) {
            $table->foreignId('base_layout_id')->primary()->constrained('base_layouts')->cascadeOnDelete();
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('bookmarks_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('copies_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            // `real` (specs/07): single precision is plenty for a ranking score.
            $table->float('trending_score', 24)->default(0);
            $table->timestampTz('score_updated_at')->nullable();
        });

        Schema::create('base_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 24)->unique();
            $table->string('slug', 24)->unique();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_suggested')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('base_layout_tag', function (Blueprint $table) {
            $table->foreignId('base_layout_id')->constrained('base_layouts')->cascadeOnDelete();
            $table->foreignId('base_tag_id')->constrained('base_tags')->cascadeOnDelete();

            $table->primary(['base_layout_id', 'base_tag_id']);
            $table->index(['base_tag_id', 'base_layout_id']);
        });

        // A user cannot republish their own live layout; other users' copies are flagged, not
        // blocked (FR-BASE-11, specs/23 §3).
        DB::statement('CREATE UNIQUE INDEX base_layouts_user_layout_unique ON base_layouts (user_id, layout_hash) WHERE deleted_at IS NULL');

        // The feed's sort columns run newest / highest first.
        $desc = DB::getDriverName() === 'pgsql' ? ' DESC' : '';
        DB::statement("CREATE INDEX base_layouts_feed_index ON base_layouts (status, visibility, published_at{$desc})");
        DB::statement("CREATE INDEX base_layouts_user_published_index ON base_layouts (user_id, published_at{$desc})");
        DB::statement("CREATE INDEX base_tags_usage_index ON base_tags (usage_count{$desc}) WHERE is_blocked = false");
        DB::statement("CREATE INDEX base_layouts_public_th_category_index ON base_layouts (th_level, category, published_at{$desc}) WHERE status = 'published' AND visibility = 'public'");
        foreach (['trending_score', 'likes_count', 'copies_count'] as $column) {
            DB::statement("CREATE INDEX base_metrics_{$column}_index ON base_metrics ({$column}{$desc})");
        }

        // Enum columns are varchar + CHECK, Postgres only: SQLite cannot add constraints.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE base_tags ALTER COLUMN name TYPE citext');
            DB::statement("ALTER TABLE base_layouts ADD CONSTRAINT base_layouts_category_check CHECK (category IN ('war','cwl','farming','trophy','legend','anti_3_star','anti_2_star','hybrid','progress','troll'))");
            DB::statement("ALTER TABLE base_layouts ADD CONSTRAINT base_layouts_visibility_check CHECK (visibility IN ('public','unlisted','private'))");
            DB::statement("ALTER TABLE base_layouts ADD CONSTRAINT base_layouts_status_check CHECK (status IN ('draft','processing','published','hidden','removed'))");
            DB::statement("ALTER TABLE base_layouts ADD CONSTRAINT base_layouts_moderation_state_check CHECK (moderation_state IN ('clean','flagged','under_review','actioned'))");
            DB::statement('ALTER TABLE base_layouts ADD CONSTRAINT base_layouts_th_level_check CHECK (th_level BETWEEN 1 AND 30)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('base_layout_tag');
        Schema::dropIfExists('base_tags');
        Schema::dropIfExists('base_metrics');
        Schema::dropIfExists('base_layouts');
    }
};
