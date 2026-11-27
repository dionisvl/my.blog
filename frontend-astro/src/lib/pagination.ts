/** Item of a pagination window: a page number or a gap marker. */
export type PageItem = number | "ellipsis";

/**
 * Page numbers to show around the current page: the first and last pages are always present,
 * `siblings` pages on each side of the current one, and "ellipsis" only where pages are skipped.
 * A gap of a single page is shown as that page, not as an ellipsis.
 */
export function paginationWindow(
  current: number,
  total: number,
  siblings = 2
): PageItem[] {
  if (!Number.isFinite(total) || total < 1) return [];
  const last = Math.floor(total);
  const page = Math.min(Math.max(1, Math.floor(current) || 1), last);

  let start = Math.max(2, page - siblings);
  let end = Math.min(last - 1, page + siblings);
  if (start === 3) start = 2;
  if (end === last - 2) end = last - 1;

  const items: PageItem[] = [1];
  if (start > 2) items.push("ellipsis");
  for (let n = start; n <= end; n++) items.push(n);
  if (end < last - 1) items.push("ellipsis");
  if (last > 1) items.push(last);
  return items;
}

/** URL of a listing page; page 1 is the bare path so it has a single address. */
export function pageHref(basePath: string, page: number): string {
  return page > 1 ? `${basePath}?page=${page}` : basePath;
}

/**
 * Parses the `page` query value. `redirect` is true when the value is not the canonical
 * spelling of the page (for example `1`, `01`, `0` or `abc`) and the request should be
 * permanently redirected to `pageHref(basePath, page)`.
 */
export function parsePageParam(raw: string | null): {
  page: number;
  redirect: boolean;
  notFound: boolean;
} {
  if (raw === null) return { page: 1, redirect: false, notFound: false };
  if (/^[1-9]\d{9,}$/.test(raw)) {
    return { page: 1, redirect: false, notFound: true };
  }
  if (/^[1-9]\d{0,8}$/.test(raw)) {
    const page = Number(raw);
    return { page, redirect: page === 1, notFound: false };
  }
  const digits = /^\d{1,9}$/.test(raw) ? Number(raw) : 0;
  return { page: Math.max(1, digits), redirect: true, notFound: false };
}

/**
 * Redirect target for a non-canonical `page` value. The path is collapsed to a single leading
 * slash (so it can never be read as a protocol-relative URL) and other query parameters are kept.
 */
export function pageRedirectLocation(
  pathname: string,
  search: URLSearchParams,
  page: number
): string {
  const path = "/" + pathname.replace(/^[/\\]+/, "");
  const rest = new URLSearchParams(search);
  rest.delete("page");
  if (page > 1) rest.set("page", String(page));
  const query = rest.toString();
  return query ? `${path}?${query}` : path;
}

/** True when the requested page lies beyond the last page reported by the API. */
export function isPageOutOfRange(page: number, totalPages: number): boolean {
  return page > 1 && page > totalPages;
}
