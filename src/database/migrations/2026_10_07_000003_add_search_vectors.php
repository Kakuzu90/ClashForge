<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Search v1 (specs/17 §3, P3-05): a `search_vector` on `profiles` and `base_layouts`, each with a
// GIN index, kept by triggers so a write and its vector change in the same transaction (specs/23
// §9). Names and tags use the `simple` configuration, prose uses `english`. CoC accounts search
// their existing `to_tsvector('simple', ign)` index. Postgres only: SQLite has no tsvector. The
// functions are replaced, not created: `migrate:fresh` drops tables but keeps functions.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE profiles ADD COLUMN search_vector tsvector');
        DB::statement('ALTER TABLE base_layouts ADD COLUMN search_vector tsvector');

        // username (A) + display name (A) + bio (C). The username lives on `users`.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION profile_search_vector(p_user_id bigint, p_display_name text, p_bio text) RETURNS tsvector
            LANGUAGE sql STABLE AS $$
                SELECT setweight(to_tsvector('simple', coalesce((SELECT username FROM users WHERE id = p_user_id), '')), 'A')
                    || setweight(to_tsvector('simple', coalesce(p_display_name, '')), 'A')
                    || setweight(to_tsvector('english', coalesce(p_bio, '')), 'C')
            $$;

            CREATE OR REPLACE FUNCTION profiles_search_vector_trigger() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                NEW.search_vector := profile_search_vector(NEW.user_id, NEW.display_name, NEW.bio);
                RETURN NEW;
            END $$;

            CREATE TRIGGER profiles_search_vector BEFORE INSERT OR UPDATE OF user_id, display_name, bio ON profiles
                FOR EACH ROW EXECUTE FUNCTION profiles_search_vector_trigger();

            CREATE OR REPLACE FUNCTION users_profile_search_vector_trigger() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE profiles SET search_vector = profile_search_vector(user_id, display_name, bio) WHERE user_id = NEW.id;
                RETURN NULL;
            END $$;

            CREATE TRIGGER users_profile_search_vector AFTER UPDATE OF username ON users
                FOR EACH ROW WHEN (OLD.username IS DISTINCT FROM NEW.username)
                EXECUTE FUNCTION users_profile_search_vector_trigger();
        SQL);

        // title (A) + tag names (B) + description (C). Tags are child rows, so the pivot and a
        // renamed tag refresh the vector too.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION base_layout_search_vector(p_id bigint, p_title text, p_description text) RETURNS tsvector
            LANGUAGE sql STABLE AS $$
                SELECT setweight(to_tsvector('english', coalesce(p_title, '')), 'A')
                    || setweight(to_tsvector('simple', coalesce((
                        SELECT string_agg(base_tags.name, ' ') FROM base_layout_tag
                        JOIN base_tags ON base_tags.id = base_layout_tag.base_tag_id
                        WHERE base_layout_tag.base_layout_id = p_id), '')), 'B')
                    || setweight(to_tsvector('english', coalesce(p_description, '')), 'C')
            $$;

            CREATE OR REPLACE FUNCTION base_layouts_search_vector_trigger() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                NEW.search_vector := base_layout_search_vector(NEW.id, NEW.title, NEW.description);
                RETURN NEW;
            END $$;

            CREATE TRIGGER base_layouts_search_vector BEFORE INSERT OR UPDATE OF title, description ON base_layouts
                FOR EACH ROW EXECUTE FUNCTION base_layouts_search_vector_trigger();

            CREATE OR REPLACE FUNCTION base_layout_tag_search_vector_trigger() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                v_id bigint := CASE WHEN TG_OP = 'DELETE' THEN OLD.base_layout_id ELSE NEW.base_layout_id END;
            BEGIN
                UPDATE base_layouts SET search_vector = base_layout_search_vector(id, title, description) WHERE id = v_id;
                RETURN NULL;
            END $$;

            CREATE TRIGGER base_layout_tag_search_vector AFTER INSERT OR DELETE ON base_layout_tag
                FOR EACH ROW EXECUTE FUNCTION base_layout_tag_search_vector_trigger();

            CREATE OR REPLACE FUNCTION base_tags_search_vector_trigger() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE base_layouts SET search_vector = base_layout_search_vector(id, title, description)
                WHERE id IN (SELECT base_layout_id FROM base_layout_tag WHERE base_tag_id = NEW.id);
                RETURN NULL;
            END $$;

            CREATE TRIGGER base_tags_search_vector AFTER UPDATE OF name ON base_tags
                FOR EACH ROW WHEN (OLD.name IS DISTINCT FROM NEW.name)
                EXECUTE FUNCTION base_tags_search_vector_trigger();
        SQL);

        DB::statement('UPDATE profiles SET search_vector = profile_search_vector(user_id, display_name, bio)');
        DB::statement('UPDATE base_layouts SET search_vector = base_layout_search_vector(id, title, description)');

        DB::statement('CREATE INDEX profiles_search_vector_index ON profiles USING gin (search_vector)');
        DB::statement('CREATE INDEX base_layouts_search_vector_index ON base_layouts USING gin (search_vector)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS base_tags_search_vector ON base_tags;
            DROP TRIGGER IF EXISTS base_layout_tag_search_vector ON base_layout_tag;
            DROP TRIGGER IF EXISTS base_layouts_search_vector ON base_layouts;
            DROP TRIGGER IF EXISTS users_profile_search_vector ON users;
            DROP TRIGGER IF EXISTS profiles_search_vector ON profiles;
            DROP FUNCTION IF EXISTS base_tags_search_vector_trigger();
            DROP FUNCTION IF EXISTS base_layout_tag_search_vector_trigger();
            DROP FUNCTION IF EXISTS base_layouts_search_vector_trigger();
            DROP FUNCTION IF EXISTS base_layout_search_vector(bigint, text, text);
            DROP FUNCTION IF EXISTS users_profile_search_vector_trigger();
            DROP FUNCTION IF EXISTS profiles_search_vector_trigger();
            DROP FUNCTION IF EXISTS profile_search_vector(bigint, text, text);
            ALTER TABLE base_layouts DROP COLUMN IF EXISTS search_vector;
            ALTER TABLE profiles DROP COLUMN IF EXISTS search_vector;
        SQL);
    }
};
