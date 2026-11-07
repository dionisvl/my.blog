import { defineMiddleware } from "astro:middleware";
import { PUBLIC_SITE_URL } from "astro:env/client";
import { isGeoIpLocaleRoutingEnabled } from "./lib/geoip-config";
import { countryForRequest } from "./lib/geoip-country";
import { GEO_STAY_COOKIE, cookieDomain, isWwwHost } from "./lib/host";

// Paths that never carry a page for the reader (API, assets, feeds); files are recognised by extension.
const EXCLUDED =
  /^\/(api|_astro|admin|admin-assets|login|logout|storage)(\/|$)|^\/(sitemap\.xml|robots\.txt|llms\.txt)$|\.[a-z0-9]{2,5}$/i;

/**
 * www.<host> is served directly to readers in Russia. Everyone else, including unknown countries,
 * is sent once to the canonical host (which sits behind Cloudflare).
 */
export const onRequest = defineMiddleware(async (context, next) => {
  const { request, url, cookies } = context;
  if (!isGeoIpLocaleRoutingEnabled() || !isWwwHost(url.hostname)) return next();
  if (request.method !== "GET" && request.method !== "HEAD") return next();
  if (EXCLUDED.test(url.pathname) || cookies.has(GEO_STAY_COOKIE)) return next();
  if ((await countryForRequest(request.headers)) === "RU") return next();

  // Assign parts separately: "//evil.example" as a path must not become a protocol-relative URL.
  const target = new URL(PUBLIC_SITE_URL);
  target.pathname = url.pathname.replace(/^\/{2,}/, "/");
  target.search = url.search;
  cookies.set(GEO_STAY_COOKIE, "1", {
    path: "/",
    domain: cookieDomain(),
    maxAge: 6 * 60 * 60,
    httpOnly: true,
    secure: url.protocol === "https:",
    sameSite: "lax",
  });
  const response = context.redirect(target.toString(), 302);
  response.headers.set("Cache-Control", "private, no-store");
  response.headers.set("Vary", "Cookie");
  return response;
});
