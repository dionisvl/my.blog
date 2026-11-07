-- Schema-only baseline of the production tables that the modular-articles migration touches or that
-- reference `posts`, copied from the read-only `pg_dump --schema-only` snapshot (PostgreSQL 17.11,
-- Laravel-era tables) taken before the migration. It contains no data and no secrets.
--
-- Notable properties the migration must be safe against:
--   * posts.id is BIGINT (sequence default), posts.status is BOOLEAN NOT NULL with NO default
--     (false = published, true = draft), posts.is_featured has no default, posts.content is NOT NULL;
--   * there is NO unique index on posts.slug and NO foreign key pointing at posts;
--   * comments.post_id / post_tags.post_id / posts_likes.post_id are plain INTEGER columns.
--
-- Statements are separated by a line consisting of "-- @@" (same convention as the migration scripts).

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255),
    is_admin boolean NOT NULL,
    status integer DEFAULT 0 NOT NULL,
    remember_token character varying(100),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    avatar character varying(255),
    email_verified_at timestamp with time zone
)
-- @@
CREATE SEQUENCE public.users_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.users ADD CONSTRAINT idx_16543_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.categories (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    detail_text text,
    preview_text text
)
-- @@
CREATE SEQUENCE public.categories_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.categories ADD CONSTRAINT idx_16392_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.tags (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
)
-- @@
CREATE SEQUENCE public.tags_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.tags ADD CONSTRAINT idx_16522_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.posts (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    content text NOT NULL,
    category_id integer,
    user_id integer,
    status boolean NOT NULL,
    views integer DEFAULT 0 NOT NULL,
    is_featured boolean NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    date date,
    image character varying(255),
    description text,
    views_count integer DEFAULT 0 NOT NULL
)
-- @@
CREATE SEQUENCE public.posts_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.posts ALTER COLUMN id SET DEFAULT nextval('public.posts_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.posts ADD CONSTRAINT idx_16485_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.post_tags (
    id bigint NOT NULL,
    post_id integer NOT NULL,
    tag_id integer NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
)
-- @@
CREATE SEQUENCE public.post_tags_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.post_tags ALTER COLUMN id SET DEFAULT nextval('public.post_tags_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.post_tags ADD CONSTRAINT idx_16477_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.posts_likes (
    id bigint NOT NULL,
    post_id integer NOT NULL,
    ip character varying(255),
    device_memory integer,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
)
-- @@
CREATE SEQUENCE public.posts_likes_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.posts_likes ALTER COLUMN id SET DEFAULT nextval('public.posts_likes_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.posts_likes ADD CONSTRAINT idx_16496_primary PRIMARY KEY (id)
-- @@
CREATE TABLE public.comments (
    id bigint NOT NULL,
    author_name text,
    text text NOT NULL,
    user_id integer NOT NULL,
    post_id integer NOT NULL,
    status integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
)
-- @@
CREATE SEQUENCE public.comments_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
-- @@
ALTER TABLE ONLY public.comments ALTER COLUMN id SET DEFAULT nextval('public.comments_id_seq'::regclass)
-- @@
ALTER TABLE ONLY public.comments ADD CONSTRAINT idx_16399_primary PRIMARY KEY (id)
