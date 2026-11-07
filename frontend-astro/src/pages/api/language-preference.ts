import type { APIRoute } from "astro";
import { PUBLIC_SITE_URL } from "astro:env/client";
import { LANGUAGE_LABELS } from "../../lib/i18n";
import { ARTICLE_LANGUAGE_COOKIE } from "../../lib/language-choice";

export const prerender = false;

const supported = new Set(Object.keys(LANGUAGE_LABELS));

export const POST: APIRoute = async ({ request, cookies, url }) => {
  const form = await request.formData();
  const locale = form.get("locale");
  const slugField = form.get("slug");
  const nextField = form.get("next");
  const slug = typeof slugField === "string" && slugField ? slugField : null;
  // Same-origin path only: a single leading slash, no scheme-relative or backslash tricks.
  const next =
    typeof nextField === "string" && /^\/(?![/\\])/.test(nextField) ? nextField : "/";

  if (
    typeof locale !== "string" ||
    !supported.has(locale) ||
    (slug !== null &&
      (slug === "." ||
        slug === ".." ||
        slug.includes("/") ||
        slug.includes("\\") ||
        slug.includes("?") ||
        slug.includes("#")))
  ) {
    return new Response("Invalid language preference", {
      status: 400,
      headers: { "Cache-Control": "private, no-store" },
    });
  }

  cookies.set(ARTICLE_LANGUAGE_COOKIE, locale, {
    path: "/",
    maxAge: 24 * 60 * 60,
    httpOnly: true,
    secure: PUBLIC_SITE_URL.startsWith("https://") || url.protocol === "https:",
    sameSite: "lax",
  });

  const response = new Response(null, {
    status: 303,
    headers: {
      Location: slug ? `/${locale}/post/${encodeURIComponent(slug)}` : next,
      "Cache-Control": "private, no-store",
      Vary: "Cookie",
    },
  });
  return response;
};
