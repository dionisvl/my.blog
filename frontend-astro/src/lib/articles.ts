import { API_URL, API_KEY } from "astro:env/server";
import type {
  ArticleLocale,
  ArticleResolution,
  LocaleLink,
  PublishedLocale,
} from "../contracts/article-blocks/v1/types";
import { ApiClientError } from "./api";
import { isArticleLocaleShape } from "./articles-core";
import type { PostResponse } from "./types";

/** GET /api/post/{slug}/locale/{locale}: only ever a published locale; 404 otherwise. */
export interface ArticleLocaleResponse extends Omit<PostResponse, "post"> {
  contract_version: 1;
  post: PostResponse["post"];
  article: PublishedLocale;
  locales: LocaleLink[];
}

async function articleFetch<T>(path: string): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    headers: { "Content-Type": "application/json", "X-API-Key": API_KEY },
  });

  if (!res.ok) {
    const body = await res.text();
    throw new ApiClientError(res.status, body, `API ${res.status}: ${path}`);
  }
  return res.json() as Promise<T>;
}

export async function getArticleLocale(
  slug: string,
  locale: ArticleLocale,
): Promise<ArticleLocaleResponse> {
  const path = `/api/post/${encodeURIComponent(slug)}/locale/${locale}`;
  const data = await articleFetch<unknown>(path);
  if (!isArticleLocaleShape(data)) {
    throw new ApiClientError(502, "", `API returned a malformed article locale: ${path}`);
  }
  return data as ArticleLocaleResponse;
}

/** Side-effect free (no view counter, no cookie), so it is safe for redirect decisions. */
export function resolveArticle(slug: string): Promise<ArticleResolution> {
  return articleFetch<ArticleResolution>(
    `/api/post/${encodeURIComponent(slug)}/resolve`,
  );
}

/** Trigger the legacy daily view-count behavior for a rendered modular locale page. */
export async function recordArticleView(
  slug: string,
): Promise<{ post?: PostResponse["post"]; counted: boolean }> {
  const path = `/api/post/${encodeURIComponent(slug)}`;
  const res = await fetch(`${API_URL}${path}`, {
    headers: { "Content-Type": "application/json", "X-API-Key": API_KEY },
  });
  const headerSaysCounted = res.headers.get("x-view-counted") === "true";
  if (!res.ok) return { counted: false };

  try {
    const data = (await res.json()) as PostResponse;
    const post = data?.post;
    if (!post || typeof post.views_count !== "number" || !Number.isFinite(post.views_count)) {
      return { counted: false };
    }
    return { post, counted: headerSaysCounted };
  } catch {
    return { counted: false };
  }
}
