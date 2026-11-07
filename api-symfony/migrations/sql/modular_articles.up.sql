-- Modular multilingual articles: additive migration anchored on the existing `posts` table.
--
-- Single source of truth for the DDL. It is executed by
--   * api-symfony/migrations/Version20261102120000.php (Doctrine Migrations), and
--   * api-go/internal/repository/testhelper (Go Postgres-backed tests),
-- so the two cannot drift. Statements are separated by a line consisting of "-- @@"
-- (plpgsql bodies contain semicolons, so a plain ";" split is not possible).
--
-- Baseline: the production schema snapshot (PostgreSQL 17.x, Laravel-era tables):
--   posts.id is BIGINT, posts.status is BOOLEAN NOT NULL WITHOUT a default
--   (status = FALSE means PUBLISHED, TRUE means DRAFT), no unique index on posts.slug,
--   and no foreign keys point at posts. Nothing below alters or rewrites existing data:
--   every added posts column is nullable or has a constant default, so existing rows
--   stay `legacy_canonical` and keep their current visibility.

ALTER TABLE posts
    ADD COLUMN route_mode VARCHAR(20) NOT NULL DEFAULT 'legacy_canonical',
    ADD COLUMN original_locale VARCHAR(5) DEFAULT NULL,
    ADD COLUMN primary_locale VARCHAR(5) DEFAULT NULL,
    ADD CONSTRAINT chk_posts_route_mode CHECK (route_mode IN ('legacy_canonical', 'neutral')),
    ADD CONSTRAINT chk_posts_original_locale CHECK (original_locale IS NULL OR original_locale IN ('ru', 'en')),
    ADD CONSTRAINT chk_posts_primary_locale CHECK (primary_locale IS NULL OR primary_locale IN ('ru', 'en')),
    ADD CONSTRAINT chk_posts_neutral_has_primary_locale CHECK (route_mode <> 'neutral' OR primary_locale IS NOT NULL)
-- @@
-- Slug uniqueness for modular anchors only: production has no unique index on posts.slug and
-- may contain duplicates, so a full unique index is not safe to add here. The publish service
-- additionally refuses a slug that any existing post already uses.
CREATE UNIQUE INDEX uniq_posts_neutral_slug ON posts (slug) WHERE route_mode = 'neutral'
-- @@
-- posts.status = FALSE means PUBLISHED. A modular anchor must therefore be created with an
-- explicit status = TRUE (draft); only the publish service flips it once a locale is live.
-- This trigger turns a forgotten/defaulted status into a loud error instead of a public blank post.
CREATE FUNCTION posts_neutral_insert_must_be_draft() RETURNS trigger AS $$
BEGIN
    IF NEW.route_mode = 'neutral' AND NEW.status = FALSE THEN
        RAISE EXCEPTION 'modular (neutral) post anchors must be inserted with status = true (draft)'
            USING ERRCODE = 'check_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
-- @@
CREATE TRIGGER trg_posts_neutral_insert_must_be_draft
    BEFORE INSERT ON posts
    FOR EACH ROW EXECUTE FUNCTION posts_neutral_insert_must_be_draft()
-- @@
-- One row per (post, locale). The content of that locale lives here: localized metadata, search
-- text and the typed block document. published_at IS NULL means the locale is a draft and is never
-- served by the public API. There is deliberately no revision history and no optimistic lock.
CREATE TABLE post_locales (
    post_id BIGINT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    schema_version SMALLINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    search_text TEXT NOT NULL DEFAULT '',
    document JSONB NOT NULL,
    published_at TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    created_by BIGINT DEFAULT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
    CONSTRAINT pk_post_locales PRIMARY KEY (post_id, locale),
    CONSTRAINT fk_post_locales_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT chk_post_locales_locale CHECK (locale IN ('ru', 'en')),
    CONSTRAINT chk_post_locales_schema_version CHECK (schema_version >= 1),
    CONSTRAINT chk_post_locales_document_object CHECK (jsonb_typeof(document) = 'object'),
    CONSTRAINT chk_post_locales_document_locale CHECK (document ->> 'locale' = locale)
)
