// Pure helpers for the modular article pages. No Astro or environment imports, so they run under
// plain `node --test` (see tests/articles-core.test.ts).

import type {
  ArticleLocale,
  ArticleResolution,
  LocaleLink,
  Rating,
} from "../contracts/article-blocks/v1/types";

export const ARTICLE_LOCALE_LIST: readonly ArticleLocale[] = ["ru", "en"];

export function isArticleLocale(value: unknown): value is ArticleLocale {
  return value === "ru" || value === "en";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/**
 * A 200 answer of the locale endpoint must carry what the page dereferences. Anything else is an
 * upstream contract violation and must fail as such, not as an incidental TypeError.
 */
export function isArticleLocaleShape(value: unknown): boolean {
  if (!isRecord(value) || !isRecord(value.post) || !Array.isArray(value.locales)) {
    return false;
  }
  const article = value.article;
  return (
    isRecord(article) &&
    typeof article.title === "string" &&
    typeof article.published_at === "string" &&
    isRecord(article.document) &&
    Array.isArray(article.document.blocks) &&
    article.document.blocks.every((block) => isRecord(block) && typeof block.type === "string" && typeof block.id === "string" && isRecord(block.props))
  );
}

export interface RedirectPlan {
  status: 301 | 302;
  location: string;
}

/** A redirect target must stay on this site: one leading slash, never `//host` or a scheme. */
function isSiteRelative(path: string): boolean {
  return path.startsWith("/") && !path.startsWith("//") && !path.includes("\\");
}

/**
 * What to do when the requested language version is not published (the locale endpoint answered 404).
 *
 * - Classified legacy post, original language requested: 301 to the old canonical `/post/{slug}`.
 * - Classified legacy post, missing translation: 302 to the original.
 * - Modular article: 302 to the primary locale when published, else to the first published one.
 * - Nothing published, or the only candidate is the page being requested: no redirect (404).
 */
export function planMissingLocaleRedirect(
  resolution: ArticleResolution,
  requested: ArticleLocale,
): RedirectPlan | null {
  const requestedPath = `/${requested}/post/${resolution.slug}`;

  if (resolution.route_mode === "legacy_canonical") {
    if (
      resolution.original_locale === requested &&
      resolution.canonical_path &&
      isSiteRelative(resolution.canonical_path)
    ) {
      return { status: 301, location: resolution.canonical_path };
    }
    const original = resolution.locales[0];
    if (original && isSiteRelative(original.url) && original.url !== requestedPath) {
      return { status: 302, location: original.url };
    }
    return null;
  }

  const published = resolution.locales;
  if (published.length === 0) {
    return null;
  }

  const target =
    published.find((link) => link.locale === resolution.primary_locale) ??
    published[0];

  if (!isSiteRelative(target.url) || target.url === requestedPath) {
    return null;
  }
  return { status: 302, location: target.url };
}

export interface Alternate {
  hreflang: string;
  path: string;
}

/**
 * Mutual hreflang links for the published versions only. `x-default` points at the stable English
 * version and only when both languages exist; it is never a neutral or redirecting URL.
 */
export function buildAlternates(locales: LocaleLink[]): Alternate[] {
  const links = locales.filter(
    (link) => isArticleLocale(link.locale) && isSiteRelative(link.url),
  );
  const alternates: Alternate[] = links.map((link) => ({
    hreflang: link.locale,
    path: link.url,
  }));

  const english = links.find((link) => link.locale === "en");
  if (english && links.some((link) => link.locale === "ru")) {
    alternates.push({ hreflang: "x-default", path: english.url });
  }
  return alternates;
}

/** Only site-relative and http(s) URLs may become an `href`; anything else collapses to `#`. */
export function safeHref(url: string): string {
  if (/^https?:\/\//i.test(url) || isSiteRelative(url)) {
    return url;
  }
  return "#";
}

export function isExternalUrl(url: string): boolean {
  return /^https?:\/\//i.test(url);
}

export function relFor(sponsored: boolean | undefined, url: string): string {
  if (sponsored) {
    return "sponsored nofollow noopener noreferrer";
  }
  return isExternalUrl(url) ? "noopener noreferrer" : "";
}

/** Fills the `{name}` and `{cta}` placeholders of a card-selector label. Plain text in, plain text out. */
export function fillTemplate(
  template: string,
  values: { name?: string; cta?: string },
): string {
  return template
    .replace(/\{name\}/g, values.name ?? "")
    .replace(/\{cta\}/g, values.cta ?? "");
}

/**
 * Owner-authored JavaScript is emitted verbatim inside an inline script. The only change is that a
 * literal `</script` cannot close the element early: `<\/script` means the same thing to a JS parser
 * in strings, template literals and regular expressions.
 */
export function inlineScriptSafe(js: string): string {
  return js.replace(/<\/(script)/gi, "<\\/$1");
}

const RATING_CLASSES: Record<Rating, string> = {
  good: "text-green-400",
  mixed: "text-yellow-400",
  bad: "text-red-400",
  neutral: "text-gray-300",
};

export function ratingClass(rating: Rating | undefined): string {
  return rating ? RATING_CLASSES[rating] : "text-gray-300";
}

export function intlLocale(locale: ArticleLocale): string {
  return locale === "ru" ? "ru-RU" : "en-US";
}

export function formatLongDate(value: string, locale: ArticleLocale): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  return new Intl.DateTimeFormat(intlLocale(locale), {
    dateStyle: "long",
    timeZone: "UTC",
  }).format(date);
}

export function relativeTime(
  value: string,
  locale: ArticleLocale,
  now: Date = new Date(),
): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ["year", 31536000],
    ["month", 2592000],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
  ];
  const formatter = new Intl.RelativeTimeFormat(intlLocale(locale), {
    numeric: "auto",
  });
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) {
      return formatter.format(Math.round(seconds / size), unit);
    }
  }
  return formatter.format(seconds, "second");
}
