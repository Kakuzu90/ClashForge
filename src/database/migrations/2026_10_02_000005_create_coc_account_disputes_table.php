<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `coc_account_disputes`, specs/13 §5.
return new class extends Migration
{
    private const ACTIVE = "('open','awaiting_admin','awaiting_claimant','awaiting_holder')";

    public function up(): void
    {
        Schema::create('coc_account_disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            // The holder's row when the dispute opened. Restrict: a disputed account is never deleted (specs/08 §2).
            $table->foreignId('coc_account_id')->constrained('coc_accounts')->restrictOnDelete();
            $table->string('tag_normalized', 14);
            $table->foreignId('claimant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            // Append-only: one entry per submission, `{party, note, media: [ulid], at}`.
            $table->jsonb('evidence')->default('[]');
            $table->string('status', 20);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->restrictOnDelete();
            // Internal: never shown to either party.
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            // Who ended it: claimant, holder, admin, token or sweep. A sweep withdrawal counts
            // toward the claimant's bar like a denial.
            $table->string('closed_by', 20)->nullable();
            // When the current wait began: the holder's 7 days, or the 30 days a party asked for
            // more has to answer.
            $table->timestampTz('awaiting_since');
            $table->timestampTz('escalated_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('assigned_admin_id');
            $table->index('coc_account_id');
            $table->index(['claimant_id', 'status']);
        });

        // One active dispute per challenger per tag (specs/07), and per held account: a disputed
        // account takes no new disputes (specs/13 §2).
        DB::statement('CREATE UNIQUE INDEX coc_account_disputes_one_active_per_claimant ON coc_account_disputes (tag_normalized, claimant_id) WHERE status IN '.self::ACTIVE);
        DB::statement('CREATE UNIQUE INDEX coc_account_disputes_one_active_per_account ON coc_account_disputes (coc_account_id) WHERE status IN '.self::ACTIVE);

        // Enum columns are varchar + CHECK, Postgres only: SQLite cannot add constraints.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE coc_account_disputes ADD CONSTRAINT coc_account_disputes_status_check CHECK (status IN ('open','awaiting_admin','awaiting_claimant','awaiting_holder','resolved_transfer','resolved_denied','resolved_suspended','withdrawn','auto_resolved'))");
            DB::statement('ALTER TABLE coc_account_disputes ADD CONSTRAINT coc_account_disputes_reason_length_check CHECK (char_length(reason) <= 1000)');
            DB::statement("ALTER TABLE coc_account_disputes ADD CONSTRAINT coc_account_disputes_closed_by_check CHECK (closed_by IN ('claimant','holder','admin','token','sweep'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coc_account_disputes');
    }
};
