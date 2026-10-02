<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_api_requests`: rolling log of outbound CoC API calls and cache hits, pruned at
// 7 days. `status_code` is nullable: a timeout has no response.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coc_api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 60);
            $table->string('tag', 16)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('was_cached')->default(false);
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('created_at');

            $table->index('created_at');
            $table->index(['endpoint', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coc_api_requests');
    }
};
