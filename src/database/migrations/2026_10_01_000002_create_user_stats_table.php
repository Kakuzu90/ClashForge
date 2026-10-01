<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `user_stats`: denormalised counters, one row per user (specs/08 1:1 cascade). They
// stay at 0 until bases ship; the listeners and the nightly recompute come with P3-04.
// `followers_count` / `following_count` arrive with follows [P2].
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->integer('bases_published')->default(0);
            $table->bigInteger('total_base_likes')->default(0);
            $table->bigInteger('total_base_copies')->default(0);
            $table->bigInteger('total_base_views')->default(0);
            $table->integer('comments_posted')->default(0);
            $table->timestampTz('recomputed_at')->nullable();
        });

        DB::table('users')->orderBy('id')->select('id')->each(function (object $user): void {
            DB::table('user_stats')->insertOrIgnore(['user_id' => $user->id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
