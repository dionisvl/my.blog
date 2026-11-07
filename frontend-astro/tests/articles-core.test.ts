import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  buildAlternates,
  fillTemplate,
  formatLongDate,
  inlineScriptSafe,
  isArticleLocale,
  isArticleLocaleShape,
  planMissingLocaleRedirect,
  relFor,
  relativeTime,
  safeHref,
} from "../src/lib/articles-core.ts";

type Resolution = Parameters<typeof planMissingLocaleRedirect>[0];

const at = "2026-10-29T10:00:00Z";

function modular(locales: ("ru" | "en")[], primary: "ru" | "en" = "ru"): Resolution {
  return {
    contract_version: 1,
    slug: "cards",
    route_mode: "neutral",
    original_locale: null,
    primary_locale: primary,
    canonical_path: null,
    locales: locales.map((locale) => ({ locale, url: `/${locale}/post/cards`, title: "t", published_at: at })),
    x_default_locale: null,
  };
}

function legacy(originalLocale: "ru" | "en", translations: ("ru" | "en")[] = []): Resolution {
  return {
    contract_version: 1,
    slug: "old",
    route_mode: "legacy_canonical",
    original_locale: originalLocale,
    primary_locale: null,
    canonical_path: "/post/old",
    locales: [
      { locale: originalLocale, url: "/post/old", title: "t", published_at: at },
      ...translations.map((locale) => ({ locale, url: `/${locale}/post/old`, title: "t", published_at: at })),
    ],
    x_default_locale: null,
  };
}

describe("isArticleLocale", () => {
  it("accepts only ru and en", () => {
    assert.equal(isArticleLocale("ru"), true);
    assert.equal(isArticleLocale("en"), true);
    for (const value of ["fr", "", "EN", undefined, null, 1]) {
      assert.equal(isArticleLocale(value), false);
    }
  });
});

describe("planMissingLocaleRedirect", () => {
  it("sends a missing modular translation to the primary locale", () => {
    assert.deepEqual(planMissingLocaleRedirect(modular(["ru"], "ru"), "en"), {
      status: 302,
      location: "/ru/post/cards",
    });
  });

  it("prefers the primary locale over other published ones", () => {
    assert.deepEqual(planMissingLocaleRedirect(modular(["en", "ru"], "ru"), "en")?.location, "/ru/post/cards");
  });

  it("falls back to the first published locale when the primary is not published", () => {
    assert.deepEqual(planMissingLocaleRedirect(modular(["en"], "ru"), "ru"), {
      status: 302,
      location: "/en/post/cards",
    });
  });

  it("returns null when nothing is published", () => {
    assert.equal(planMissingLocaleRedirect(modular([]), "en"), null);
  });

  it("never redirects a page to itself", () => {
    assert.equal(planMissingLocaleRedirect(modular(["en"], "en"), "en"), null);
  });

  it("moves the original language of a classified legacy post to its canonical URL with 301", () => {
    assert.deepEqual(planMissingLocaleRedirect(legacy("ru"), "ru"), { status: 301, location: "/post/old" });
  });

  it("sends a missing legacy translation to the original with 302", () => {
    assert.deepEqual(planMissingLocaleRedirect(legacy("ru"), "en"), { status: 302, location: "/post/old" });
  });

  it("refuses off-site and protocol-relative targets", () => {
    const evil = modular(["ru"]);
    evil.locales[0].url = "//evil.example/x";
    assert.equal(planMissingLocaleRedirect(evil, "en"), null);

    const evilCanonical = legacy("ru");
    evilCanonical.canonical_path = "https://evil.example/x";
    assert.equal(planMissingLocaleRedirect(evilCanonical, "ru")?.status, 302);
  });
});

describe("buildAlternates", () => {
  const link = (locale: "ru" | "en") => ({ locale, url: `/${locale}/post/cards`, title: "t", published_at: at });

  it("lists published versions and adds x-default only when both exist", () => {
    assert.deepEqual(buildAlternates([link("ru")]), [{ hreflang: "ru", path: "/ru/post/cards" }]);
    assert.deepEqual(buildAlternates([link("ru"), link("en")]), [
      { hreflang: "ru", path: "/ru/post/cards" },
      { hreflang: "en", path: "/en/post/cards" },
      { hreflang: "x-default", path: "/en/post/cards" },
    ]);
  });

  it("drops entries with unsafe URLs", () => {
    const bad = { ...link("en"), url: "https://evil.example/" };
    assert.deepEqual(buildAlternates([bad]), []);
  });
});

describe("isArticleLocaleShape", () => {
  const good = () => ({
    post: { id: 1 },
    locales: [],
    article: {
      title: "t",
      published_at: at,
      document: { blocks: [{ id: "a", type: "richText", props: { html: "<p>x</p>" } }] },
    },
  });

  it("accepts a complete payload", () => {
    assert.equal(isArticleLocaleShape(good()), true);
  });

  it("rejects payloads the page would crash on", () => {
    const noBlocks = good();
    (noBlocks.article.document as { blocks?: unknown }).blocks = undefined;
    const badLocales = { ...good(), locales: null };
    const badBlock = good();
    badBlock.article.document.blocks = [{ id: "a", type: "richText" } as never];
    for (const value of [null, [], {}, noBlocks, badLocales, badBlock, { ...good(), article: null }]) {
      assert.equal(isArticleLocaleShape(value), false);
    }
  });
});

describe("links", () => {
  it("keeps only http(s) and site-relative hrefs", () => {
    assert.equal(safeHref("https://example.com/a"), "https://example.com/a");
    assert.equal(safeHref("/ru/post/x"), "/ru/post/x");
    for (const url of ["javascript:alert(1)", "data:text/html,x", "//evil.example", "mailto:a@b.c", "ftp://x"]) {
      assert.equal(safeHref(url), "#");
    }
  });

  it("marks sponsored links and opens external ones safely", () => {
    assert.equal(relFor(true, "https://p.example"), "sponsored nofollow noopener noreferrer");
    assert.equal(relFor(false, "https://p.example"), "noopener noreferrer");
    assert.equal(relFor(false, "/local"), "");
  });
});

describe("text helpers", () => {
  it("fills selector placeholders without interpreting markup", () => {
    assert.equal(fillTemplate("Selected: {name} / {cta}", { name: "<b>A</b>", cta: "Go" }), "Selected: <b>A</b> / Go");
    assert.equal(fillTemplate("{name}{name}", {}), "");
  });

  it("neutralises only a closing script tag in owner JavaScript", () => {
    assert.equal(inlineScriptSafe('const s = "</script><b>";'), 'const s = "<\\/script><b>";');
    assert.equal(inlineScriptSafe("</SCRIPT>"), "<\\/SCRIPT>");
    assert.equal(inlineScriptSafe("const x = a < b && c > d;"), "const x = a < b && c > d;");
  });

  it("formats dates per language and tolerates bad input", () => {
    assert.match(formatLongDate(at, "en"), /October 29, 2026/);
    assert.match(formatLongDate(at, "ru"), /29 октября 2026/);
    assert.equal(formatLongDate("not a date", "en"), "");
  });

  it("formats relative time per language", () => {
    const now = new Date("2026-10-29T12:00:00Z");
    assert.match(relativeTime("2026-10-29T10:00:00Z", "en", now), /2 hours ago/);
    assert.match(relativeTime("2026-10-29T10:00:00Z", "ru", now), /2 час/);
    assert.equal(relativeTime("nope", "en", now), "");
  });
});
