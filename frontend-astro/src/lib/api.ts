import { API_URL, API_KEY } from "astro:env/server";
import type {
  HomeResponse,
  PostResponse,
  TagResponse,
  CategoryResponse,
  SearchResponse,
} from "./types";

export class ApiClientError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: string,
    message: string,
  ) {
    super(message);
    this.name = "ApiClientError";
  }
}

// Minimal view of Astro's APIContext: enough to forward/return cookies during SSR.
type CookieContext = {
  request: Request;
  response: { headers: Headers };
};

async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
  ctx?: CookieContext,
): Promise<T> {
  const url = `${API_URL}${path}`;
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    "X-API-Key": API_KEY,
    ...(init.headers as Record<string, string>),
  };

  // Forward the browser cookies so Go's per-day view/like gates work behind SSR.
  const cookie = ctx?.request.headers.get("cookie");
  if (cookie) {
    headers["Cookie"] = cookie;
  }

  const res = await fetch(url, { ...init, headers });

  // Propagate Set-Cookie from Go back to the browser.
  const setCookie = res.headers.getSetCookie?.() ?? [];
  for (const value of setCookie) {
    ctx?.response.headers.append("Set-Cookie", value);
  }

  if (!res.ok) {
    const body = await res.text();
    throw new ApiClientError(res.status, body, `API ${res.status}: ${path}`);
  }

  return res.json() as Promise<T>;
}

// ---- Read endpoints ----

function langQuery(lang?: string): string {
  return lang ? `&lang=${encodeURIComponent(lang)}` : "";
}

export function getHome(page = 1, lang?: string): Promise<HomeResponse> {
  return apiFetch<HomeResponse>(`/api/?page=${page}${langQuery(lang)}`);
}

export function getPost(
  slug: string,
  ctx?: CookieContext,
): Promise<PostResponse> {
  return apiFetch<PostResponse>(
    `/api/post/${encodeURIComponent(slug)}`,
    {},
    ctx,
  );
}

export function getTag(slug: string, page = 1, lang?: string): Promise<TagResponse> {
  return apiFetch<TagResponse>(
    `/api/tag/${encodeURIComponent(slug)}?page=${page}${langQuery(lang)}`,
  );
}

export function getCategory(slug: string, page = 1, lang?: string): Promise<CategoryResponse> {
  return apiFetch<CategoryResponse>(
    `/api/category/${encodeURIComponent(slug)}?page=${page}${langQuery(lang)}`,
  );
}

export function search(q: string, lang?: string): Promise<SearchResponse> {
  return apiFetch<SearchResponse>(`/api/search?q=${encodeURIComponent(q)}${langQuery(lang)}`);
}

// ---- Write endpoints (called from Astro API routes) ----

export function postComment(body: {
  message: string;
  post_id: number;
  countMe: number;
  honeypot: string;
}): Promise<unknown> {
  return apiFetch("/api/comment", {
    method: "POST",
    body: JSON.stringify(body),
  });
}
