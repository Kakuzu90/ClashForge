<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_accounts.last_viewed_at`: a signed-in view of the account page, written at most
// hourly, makes the account hot for the sync scheduler (specs/09 §6, P2-20).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coc_accounts', function (Blueprint $table) {
            $table->timestampTz('last_viewed_at')->nullable()->after('api_sync_failures');
        });
    }

    public function down(): void
    {
        Schema::table('coc_accounts', function (Blueprint $table) {
            $table->dropColumn('last_viewed_at');
        });
    }
};
