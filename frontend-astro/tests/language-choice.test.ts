import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  ARTICLE_LANGUAGE_COOKIE,
  parseLanguagePreference,
  selectPublishedLocale,
  trustedClientIp,
} from "../src/lib/language-choice.ts";
import type { ArticleResolution } from "../src/contracts/article-blocks/v1/types";

function resolution(locales: ("ru" | "en")[], primary: "ru" | "en" = "ru"): ArticleResolution {
  return {
    contract_version: 1,
    slug: "cards",
    route_mode: "neutral",
    original_locale: null,
    primary_locale: primary,
    canonical_path: null,
    locales: locales.map((locale) => ({
      locale,
      url: `/${locale}/post/cards`,
      title: locale,
      published_at: "2026-11-01T00:00:00Z",
    })),
    x_default_locale: locales.includes("en") && locales.includes("ru") ? "en" : null,
  };
}

describe("manual language preference", () => {
  it("accepts exactly one supported language cookie", () => {
    assert.equal(parseLanguagePreference(`${ARTICLE_LANGUAGE_COOKIE}=ru; other=x`), "ru");
    assert.equal(parseLanguagePreference(`${ARTICLE_LANGUAGE_COOKIE}=en`), "en");
    assert.equal(parseLanguagePreference(`${ARTICLE_LANGUAGE_COOKIE}=fr`), null);
    assert.equal(parseLanguagePreference(`${ARTICLE_LANGUAGE_COOKIE}=ru; ${ARTICLE_LANGUAGE_COOKIE}=en`), null);
    assert.equal(parseLanguagePreference(null), null);
  });
});

describe("trusted client address", () => {
  it("accepts a single valid IPv4 or IPv6 X-Real-IP value", () => {
    assert.equal(trustedClientIp(new Headers({ "x-real-ip": "203.0.113.7" })), "203.0.113.7");
    assert.equal(trustedClientIp(new Headers({ "x-real-ip": "2001:db8::1" })), "2001:db8::1");
  });

  it("rejects lists and ignores other client-supplied forwarding headers", () => {
    assert.equal(trustedClientIp(new Headers({ "x-real-ip": "203.0.113.7, 10.0.0.1" })), null);
    assert.equal(
      trustedClientIp(new Headers({
        "x-forwarded-for": "203.0.113.7",
        "true-client-ip": "203.0.113.7",
        forwarded: "for=203.0.113.7",
      })),
      null,
    );
  });
});

describe("neutral article locale selection", () => {
  it("prioritizes a published manual choice over geography", () => {
    assert.equal(selectPublishedLocale(resolution(["ru", "en"]), "ru", "US"), "ru");
  });

  it("uses RU for Russia and EN for other or unknown countries when available", () => {
    const both = resolution(["ru", "en"]);
    assert.equal(selectPublishedLocale(both, null, "RU"), "ru");
    assert.equal(selectPublishedLocale(both, null, "US"), "en");
    assert.equal(selectPublishedLocale(both, null, null), "en");
  });

  it("falls back to the available locale if the preferred language is unpublished", () => {
    assert.equal(selectPublishedLocale(resolution(["ru"], "en"), "en", "US"), "ru");
    assert.equal(selectPublishedLocale(resolution(["en"], "ru"), null, "RU"), "en");
  });

  it("returns null when the article has no published locales", () => {
    assert.equal(selectPublishedLocale(resolution([]), null, "RU"), null);
  });
});
