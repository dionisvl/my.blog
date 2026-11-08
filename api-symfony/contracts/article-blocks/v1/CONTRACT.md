# Modular article contract, version 1

Single reference for the data model, the block document and the Go read API of multilingual modular
articles. Consumers: Symfony (validates and publishes), Go (reads published locales), Astro (renders;
Stories 2-4). Nothing here contains secrets or real financial facts: fixtures use fictional providers.

## Files and drift control

| File | Role |
| --- | --- |
| `schema.json` | JSON Schema (2020-12) of the *publishable* document. Source of truth for shape. |
| `fixtures/valid/*.json` | Golden documents (`cards-ru`, `cards-en`, `minimal-en`). Must validate in publication mode and be a fixed point of the sanitizer. |
| `fixtures/invalid/*.json` | `{description, mode, expect_codes, document}`. The validator must report *at least* the listed codes in the given mode. |
| `MANIFEST.sha256` | `sha256sum`-style hashes of `schema.json` and every fixture, sorted by path. |
| `frontend-astro/src/contracts/article-blocks/v1/` | Byte-identical vendored copy (+ hand-written `types.ts`). |

Tests that fail on drift: `ArticleContractTest` (PHP: constants vs schema, manifest, vendored copy) and
`internal/model/contract_test.go` (Go: manifest, vendored copy, fixture shape, schema constants).
The full Go API response examples live in `api-go/internal/handler/testdata/{resolve-bilingual,locale-en}.json`;
`TestArticleAPI_GoldenResponses` compares them with actual HTTP handler output.
Changing a contract file means: edit the source copy, copy it to the frontend, regenerate the manifest
(`sha256sum schema.json fixtures/*/*.json`, paths relative to this directory, `LC_ALL=C` order), update
`types.ts`. A breaking change gets a new `v2` directory and a new `schema_version`; `v1` stays readable. Additive
changes stay in `v1`: the `customCode` block was added in place by owner decision.

## Data model (PostgreSQL, additive migration `Version20261102120000`)

The DDL lives in `api-symfony/migrations/sql/modular_articles.{up,down}.sql` (statements split on a
`-- @@` line) and is executed by both Doctrine Migrations and the Go Postgres tests.

* `posts` stays the stable identity (comments, likes, tags, categories, counters, slug). New columns:
  `route_mode` (`legacy_canonical` default | `neutral`), `original_locale` (nullable `ru|en`),
  `primary_locale` (nullable `ru|en`, required when `neutral`). Existing rows are untouched.
* **`posts.status` is inverted**: `false` = PUBLISHED, `true` = DRAFT. Production has no default for it,
  the ORM/Go test schema default to `false` (published). Therefore a modular anchor is **always inserted
  with `status = true`** (`Post::markAsModularDraft()`); a DB trigger rejects a `neutral` insert with
  `status = false`; and every public Go query additionally requires, for `neutral` rows, at least one
  locale that is published (`publishedVisibilityExpr`). The publisher flips `status` to `false`
  in the same transaction as the first publish and back to `true` when the last locale is unpublished.
* `post_locales (post_id, locale)` -- the content of one locale: `schema_version`, localized `title`,
  `summary`, `meta_title`, `meta_description`, `search_text`, `document` (JSONB, ordered blocks),
  `published_at`. `published_at IS NULL` means draft and is never served by the public API. There is no
  revision history and no optimistic lock (owner decision): saving a locale overwrites it, and saving an
  already published locale is live immediately, so it is validated in full publication mode. Facts live per
  locale: editing the other locale never changes this locale's text.
* A classified legacy post (`original_locale` is `ru` or `en`) may have a published locale only for
  the *other* locale. Its original stays in `posts.content` at `/post/{slug}`; publishing or withdrawing
  the translation never rewrites its original fields, status or canonical URL. Unclassified legacy
  posts cannot acquire a translation until an editor verifies and sets `original_locale`; a translation
  cannot be published while the original legacy post is still a draft.
* Card sync (publish/unpublish): the anchor's `title`, `description` (HTML-**escaped** plain summary --
  the frontend renders `posts.description` raw) and `content` (`<p>` + escaped summary, a readable
  fallback for old code) follow the primary locale's published content, else the first published one.
  Go additionally overrides `title`/`description` from the published locale on every modular read.
  On PostgreSQL, publish/unpublish first locks the `posts` row, refreshes the locale and only
  then changes its publication state, so different-locale writers cannot derive stale anchor visibility.
* Slugs: production has no unique index on `posts.slug`, so legacy data can hold duplicate slugs. The migration
  adds only a *partial* unique index for `neutral` rows. `ArticlePublisher::createAnchor()` refuses a slug
  occupied by any existing post, so a new modular anchor never takes a slug that is already in use. A legacy
  translation publish refuses an ambiguous duplicate slug with `slug_ambiguous` instead of guessing a post.
  The Go resolver, locale and detail reads resolve a duplicate slug to the same post: the lowest visible post
  id, using the public visibility rules of the read path.
* Rollback order: first hide neutral anchors (`UPDATE posts SET status = TRUE WHERE route_mode = 'neutral'`),
  then revert application code. Leave the additive schema and published locales in place for a code-only
  rollback; re-deploy the new code and resync `posts.status` from published locales before restoring
  modular visibility. Only after the old code is safely serving may `down.sql` be considered: it hides
  neutral anchors again and then drops the new objects, **destroying modular translations**.
  Do not run `down.sql` before reverting the new Go/ORM code, which reads those columns. After `down`,
  former anchors are ordinary legacy drafts; keep them hidden and do not edit/publish them with old admin.
* Deploy order: migrate first, then Symfony/Go (the new Go and ORM models select the new `posts` columns;
  old code ignores them).

## Document

```json
{ "schema_version": 1, "locale": "ru|en", "verified_at": "YYYY-MM-DD (optional)",
  "blocks": [ { "id": "unique-slug-id", "type": "<one of the block types>", "props": { } } ] }
```

Limits: 60 blocks, 256 KiB JSON, ids `^[a-z][a-z0-9_-]{0,39}$`, text bounds per field (see schema).
Strings are plain text (control characters rejected) except `richText.html` and `faq.items[].answerHtml`:
server-sanitized HTML, allow-list `p br strong b em i u ul ol li a h2 h3 h4 blockquote code pre`; `a` keeps
only `href` (http/https/site-relative), `rel` (`noopener` always; `noreferrer nofollow sponsored` if
present) and `target="_blank"`. Stored HTML must equal `sanitize(html)` (`html_not_sanitized` otherwise).

### Raw code block (owner decision, first release)

`customCode` carries `html`, `css` and `js` strings (each up to 100000 bytes, all optional). They are stored
**verbatim and are never sanitized**: the only author is the owner, so the first release trades safety checks
for speed. A trusted component registry and an unsafe-block validator are future work, needed before any other
author is admitted. `customCode` bypasses the allow-list above; every other block keeps its rules.

### Block catalogue and the `cards.html` structure it must express

| Block | Props (R = required) | `cards.html` part |
| --- | --- | --- |
| `richText` | `html` R, `variant` default/lead/callout | eyebrow, lead paragraph, "Совет" warning box |
| `sponsoredDisclosure` | `text` R, `learnMoreLabel`, `learnMoreUrl` | *(new)* advertising disclosure |
| `cardSelector` | `heading`, `groupLabel`, `targetBlockId`, `defaultOptionId`, `options[{id,label,columnId}]` R, `labels{selectedTable,stickyCaption,ctaAria,mobileHint}` R | `.mobile-compare` buttons + the strings the inline script writes (`aria-label`, sticky caption/ARIA, hint) |
| `comparisonTable` | `caption`, `criteriaLabel`, `legend[]`, `columns[{id,name,subtitle,link}]` 2-8, `rows[{id,label,cells[{rating,value,note}]}]`, `ctaRow{label,items[{columnId,...Cta}]}` | `.sheet` table: 4 product columns, 9 criteria rows + the CTA row, g/m/b dots, legend |
| `productCard` | `title` R, `badge`, `tag`, `summary`, `bullets[]`, `rating`, `cta`, `secondaryCta`, `note` | `.pick` ("best choice") and the four `.verdict .vc` cards |
| `faq` | `heading`, `items[{id,question,answerHtml}]` | `<details>` FAQ (answers contain sponsored links) |
| `cta` | `label,url,variant,sponsored` R, `title`, `text`, `sticky` | `.stickybar` and stand-alone buttons |
| `sources` | `heading`, `items[{id,label,url,sponsored?,publisher,accessed_at}]` | numbered "Источники" list; explicitly mark links to affiliate hosts sponsored |

Representable: all 9 criteria rows and the CTA row, the legend, the 4 products with sponsored destinations, the
per-card CTA row, the mobile selector with its JS-generated strings, FAQ with inline links, 12 sources,
the sticky bar. Not yet verified (Story 5): financial facts, affiliate legality, JS behaviour at 402 px.

### Validation

`ArticleDocumentValidator` has two modes. **draft** (every save): safety only -- unknown blocks/fields,
types, bounds, URL policy, affiliate rules, sanitized HTML, duplicate ids; missing required props allowed.
**publication**: additionally required props, min counts, `no_blocks`, cross references (`bad_reference`:
cardSelector -> comparisonTable -> columns), `cell_count_mismatch`, `empty_value`, Sponsored-link disclosure requirements remain unchanged; `verified_at` is optional even when sponsored links are present, and any supplied value must be a real calendar date. Metadata: title and summary non-empty.

Codes: `invalid_type unknown_field missing_field empty_value too_long too_many_items too_few_items
invalid_enum invalid_pattern invalid_date invalid_encoding control_characters bad_template
unsupported_schema_version unsupported_locale locale_mismatch unknown_block_type duplicate_id
invalid_url url_scheme_not_allowed affiliate_host_not_allowed affiliate_not_sponsored html_not_sanitized
bad_reference cell_count_mismatch no_blocks document_too_large`.

Affiliate policy: hosts in `app.article.affiliate_hosts` (host names only) require links to be marked `sponsored: true` (`rel` contains `sponsored`), except clean citation URLs inside the `sources` block whose path matches `/help-center/article/<slug>` and has no query or fragment. Those help-center citations may remain unsponsored. Every other link to an affiliate host must be sponsored; a sponsored link may only target an allow-listed affiliate host.

URL policy (applies to every URL field, including affiliate checks):

* Absolute URL schemes are matched case-sensitively: only lowercase `http://` and `https://` are accepted.
  A mixed- or upper-case scheme (for example `HTTPS://`) is not an accepted absolute URL and is rejected.
* Hosts that are percent-encoded or contain non-ASCII characters are rejected. An encoded or Unicode spelling
  of an affiliate host therefore cannot pass as a different host and bypass the affiliate rules.
* Affiliate host matching normalizes one trailing dot: `shop.example.` matches the `shop.example` entry in
  `app.article.affiliate_hosts`, so it must be linked as sponsored.

## Go read API (all under `/api`, `X-API-Key` like the others)

| Request | Result |
| --- | --- |
| `GET /api/post/{slug}/resolve` | `200` `ArticleResolution`; `404 {"error":"post not found"}` for unknown/unpublished slugs and for neutral anchors with no published locale. `Cache-Control: private, no-store`. **No view increment, no cookie.** |
| `GET /api/post/{slug}/locale/{locale}` | `200` `{contract_version, post, article, locales, featured_posts, recent_posts, categories, aphorism?}` for a published modular locale or classified legacy translation; `404` for unsupported locale (anything but `ru`/`en`), unknown slug, unclassified legacy or its original locale, or unpublished translation. No view increment, no cookie. |
| `/api/post/{slug}`, `/api/`, `/api/search`, `/api/tag/..`, `/api/category/..` | Unchanged; JSON only gains `route_mode`, and for modular or classified legacy cards `locales`, `primary_locale`, `original_locale` (omitted when null). Legacy original card fields stay unchanged. |

`ArticleResolution`: `contract_version`, `slug`, `route_mode`, `original_locale` (nullable), `primary_locale`
(nullable), `canonical_path` (`/post/{slug}` for legacy, `null` for modular), `locales[]` (`{locale, url,
title, published_at}`; modular: published variants ordered by publish time; classified legacy: original
`/post/{slug}` first, then published translations; unclassified legacy: `[]`), `x_default_locale` (`"en"`
only when both modular locales are published, `null` for legacy). `article` = `{locale,
schema_version, published_at, title,
summary, meta_title, meta_description, document}`; text is plain (renderers escape), `document` is the
stored JSONB (key order is not preserved). `post.title/description` are card fields; pages use `article.*`.
Drafts and preview data are never reachable through any public endpoint; previews (Stories
2-3) will use a separate token-scoped lookup.

Route truth table for Stories 2-4 (unchanged from the plan): legacy `/post/{slug}` 200 as today; modular
neutral `/post/{slug}` 302 `no-store` to preference > geo > available, 404 when nothing is published;
`/{ru|en}/post/{slug}` modular published -> 200 self-canonical, missing locale -> 302 `no-store` to an
available one; legacy original locale -> 301 to `/post/{slug}`, published translation -> 200, absent
translation -> 302 `no-store` to the original; other prefixes 404. Use `resolve` for the
decision, never `/api/post/{slug}` (it counts views and sets a cookie).

## Inventory of the live schema snapshot (read-only, before the migration)

PostgreSQL 17.11. Tables of interest: `posts` (BIGINT id; `status BOOLEAN NOT NULL` **without default**,
`is_featured BOOLEAN NOT NULL` without default, `content TEXT NOT NULL`, no unique index on `slug`, no
foreign keys to or from `posts`), `comments` / `posts_likes` / `post_tags` (plain `INTEGER post_id`),
`users`, `categories`, `tags`. A Laravel `migrations` table exists; Doctrine will add its own
`doctrine_migration_versions`. A faithful schema-only copy of the relevant tables is
`api-go/internal/repository/testdata/prod_baseline.sql`; the Go migration test restores it, adds legacy
rows (including duplicate slugs), applies up, checks nothing changed, applies down and up again.
The live sitemap file (`public/seo/sitemap-web3main.pro.xml`) is a standard XML `urlset`: 255
`web3main.pro` URLs, each with `lastmod`, no `xhtml:link` alternates (read-only check 2026-11-03).
The original `cards.html` is 57,551 bytes: one comparison table (11 rows including header/CTA),
four FAQ `<details>`, one mobile selector, one featured pick, four verdict cards, one sticky bar,
and one inline script. It has 30 outbound anchors, including affiliate-host links; Story 5 must
verify each destination, dated claim, translated dynamic label and responsive behavior before release.
The schema-only snapshot is **not** a recoverable data backup. Before any production migration in
Story 5, take a private full PostgreSQL backup, restore it into isolated PostgreSQL 17, verify rows
and constraints, then record rollback identifiers outside Git. No production data backup or deploy
is claimed by Story 1's isolated migration tests.

## Verification status (Story 1)

* The real Doctrine migration `Version20261102120000` and the sequence PHP publisher -> Go read -> unpublish
  -> `down` were exercised against a disposable database built from the schema-only production snapshot.
  That database is not production and holds no production data.
* Migrated-schema smoke test: `api-symfony/tests/Support/article_migrated_schema_smoke.php`, run through the
  wrapper `api-symfony/tests/Support/run_migrated_smoke.sh [publish|unpublish]`. The wrapper takes the
  disposable database name from `ARTICLE_SMOKE_DB_BASE`, appends `_test`, and refuses any name not ending in
  `_test`; `DATABASE_URL` credentials are never printed.
* GitHub Actions has **not** run this change. Production has **not** changed: no migration, deploy or backup
  was performed against it, and no backup success is claimed.
