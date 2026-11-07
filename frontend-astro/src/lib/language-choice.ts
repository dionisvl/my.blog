import { isIP } from "node:net";
import type {
  ArticleLocale,
  ArticleResolution,
} from "../contracts/article-blocks/v1/types";

export const ARTICLE_LANGUAGE_COOKIE = "modular_article_language";

export function parseLanguagePreference(cookieHeader: string | null): ArticleLocale | null {
  if (!cookieHeader) return null;

  const matches = cookieHeader
    .split(";")
    .map((part) => part.trim())
    .filter((part) => part.startsWith(`${ARTICLE_LANGUAGE_COOKIE}=`));
  if (matches.length !== 1) return null;

  const value = matches[0].slice(ARTICLE_LANGUAGE_COOKIE.length + 1);
  return value === "ru" || value === "en" ? value : null;
}

/** Only Traefik's overwritten X-Real-Ip is trusted; never parse client-supplied forwarding chains. */
export function trustedClientIp(headers: Pick<Headers, "get">): string | null {
  const value = headers.get("x-real-ip")?.trim();
  if (!value || value.includes(",") || isIP(value) === 0) return null;
  return value;
}

export function selectPublishedLocale(
  resolution: ArticleResolution,
  preference: ArticleLocale | null,
  countryCode: string | null,
): ArticleLocale | null {
  const available = new Set(
    (Array.isArray(resolution.locales) ? resolution.locales : [])
      .map((locale) => locale.locale)
      .filter((locale): locale is ArticleLocale => locale === "ru" || locale === "en"),
  );
  if (available.size === 0) return null;
  if (preference && available.has(preference)) return preference;

  // Unknown countries deliberately follow the English default when it is published.
  const geographicDefault: ArticleLocale = countryCode?.toUpperCase() === "RU" ? "ru" : "en";
  if (available.has(geographicDefault)) return geographicDefault;

  if (
    (resolution.primary_locale === "ru" || resolution.primary_locale === "en") &&
    available.has(resolution.primary_locale)
  ) {
    return resolution.primary_locale;
  }

  return available.has("en") ? "en" : "ru";
}
