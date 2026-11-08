import { PUBLIC_SITE_URL } from "astro:env/client";
import type { ArticleLocale } from "../contracts/article-blocks/v1/types";
import { isGeoIpLocaleRoutingEnabled } from "./geoip-config";
import { parseLanguagePreference } from "./language-choice";

const siteHost = new URL(PUBLIC_SITE_URL).hostname;

/** Set when a reader was already sent to the other host once, so nobody is bounced back and forth. */
export const GEO_STAY_COOKIE = "geo_stay";

/** `www.<site host>` is the direct (uncached) host served to readers in Russia. */
export function isWwwHost(hostname: string): boolean {
  return hostname === `www.${siteHost}`;
}

/** Shared by both hosts so a language choice survives a host change; unset for localhost and IPs. */
export function cookieDomain(): string | undefined {
  const isName = /^[a-z0-9-]+(\.[a-z0-9-]+)+$/i.test(siteHost) && !/^\d+(\.\d+){3}$/.test(siteHost);
  return isName ? `.${siteHost}` : undefined;
}

export function preferredLanguage(request: Request, hostname: string): ArticleLocale | null {
  const chosen = parseLanguagePreference(request.headers.get("cookie"));
  if (chosen) return chosen;
  return isGeoIpLocaleRoutingEnabled() && isWwwHost(hostname) ? "ru" : null;
}
