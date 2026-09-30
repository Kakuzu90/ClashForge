<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// specs/07 `profiles`, one per user (FR-PROFILE-1, specs/08 1:1 cascade). `languages` is a
// varchar(5)[] with a length CHECK and GIN index on Postgres, JSON on SQLite. `search_vector`
// comes with Search v1 (P3-05): it reads `users.username`, so it cannot be a generated column.
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::create('profiles', function (Blueprint $table) use ($pgsql) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 50)->nullable();
            $table->string('bio', 500)->nullable();
            $table->foreignId('avatar_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->char('country_code', 2)->nullable()->index();
            if (! $pgsql) {
                $table->json('languages')->default('[]');
            }
            $table->string('timezone', 64)->nullable();
            $table->jsonb('socials')->default('{}');
            $table->timestampsTz();
        });

        if ($pgsql) {
            DB::statement("ALTER TABLE profiles ADD COLUMN languages varchar(5)[] NOT NULL DEFAULT '{}'");
            DB::statement('ALTER TABLE profiles ADD CONSTRAINT profiles_languages_max CHECK (coalesce(array_length(languages, 1), 0) <= 3)');
            DB::statement('CREATE INDEX profiles_languages_gin ON profiles USING gin (languages)');
        }

        // Users created before this migration get their profile now.
        $now = Date::now();
        DB::table('users')->orderBy('id')->select('id')->each(function (object $user) use ($now): void {
            DB::table('profiles')->insertOrIgnore(['user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
