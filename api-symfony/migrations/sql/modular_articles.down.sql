-- Reverts modular_articles.up.sql. DESTRUCTIVE: every modular locale is dropped.
-- Statements are separated by a line consisting of "-- @@" (see the up script).
--
-- Modular anchors stay behind as plain `posts` rows, and old code would show them as blank
-- public posts when status = FALSE. They are hidden (status = TRUE = draft) FIRST.

UPDATE posts SET status = TRUE WHERE route_mode = 'neutral'
-- @@
DROP TABLE IF EXISTS post_locales
-- @@
DROP TRIGGER IF EXISTS trg_posts_neutral_insert_must_be_draft ON posts
-- @@
DROP FUNCTION IF EXISTS posts_neutral_insert_must_be_draft()
-- @@
DROP INDEX IF EXISTS uniq_posts_neutral_slug
-- @@
ALTER TABLE posts
    DROP CONSTRAINT IF EXISTS chk_posts_neutral_has_primary_locale,
    DROP CONSTRAINT IF EXISTS chk_posts_primary_locale,
    DROP CONSTRAINT IF EXISTS chk_posts_original_locale,
    DROP CONSTRAINT IF EXISTS chk_posts_route_mode,
    DROP COLUMN IF EXISTS primary_locale,
    DROP COLUMN IF EXISTS original_locale,
    DROP COLUMN IF EXISTS route_mode
