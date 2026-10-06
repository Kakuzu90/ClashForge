<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/16 §2: the holder's reminders on day 3 and day 6 of each wait, each sent once (P2-18).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coc_account_disputes', function (Blueprint $table) {
            // Reminders sent in the current holder wait; back to 0 when a new one starts.
            $table->unsignedSmallInteger('holder_reminders_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('coc_account_disputes', function (Blueprint $table) {
            $table->dropColumn('holder_reminders_sent');
        });
    }
};
