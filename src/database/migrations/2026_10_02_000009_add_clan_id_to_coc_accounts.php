<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_accounts.clan_id`, set beside `clan_tag` on attach and sync (P2-13).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coc_accounts', function (Blueprint $table) {
            $table->foreignId('clan_id')->nullable()->after('clan_tag')->constrained('clans')->nullOnDelete();
            $table->index('clan_id');
        });

        $this->restorePartialIndexes();
    }

    public function down(): void
    {
        Schema::table('coc_accounts', function (Blueprint $table) {
            $table->dropIndex(['clan_id']);
            $table->dropConstrainedForeignId('clan_id');
        });

        $this->restorePartialIndexes();
    }

    /**
     * SQLite adds a foreign key by rebuilding the table, and the rebuild recreates the partial
     * unique indexes without their WHERE clause: one row per user, one row per tag. Postgres
     * alters in place and keeps them.
     */
    private function restorePartialIndexes(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS coc_accounts_one_verified_owner');
        DB::statement('DROP INDEX IF EXISTS coc_accounts_one_featured');
        DB::statement("CREATE UNIQUE INDEX coc_accounts_one_verified_owner ON coc_accounts (tag_normalized) WHERE status IN ('verified', 'disputed')");
        DB::statement('CREATE UNIQUE INDEX coc_accounts_one_featured ON coc_accounts (user_id) WHERE is_featured');
    }
};
