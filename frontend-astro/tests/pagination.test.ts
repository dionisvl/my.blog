import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  isPageOutOfRange,
  pageHref,
  pageRedirectLocation,
  paginationWindow,
  parsePageParam,
} from "../src/lib/pagination.ts";

const E = "ellipsis";

describe("paginationWindow", () => {
  it("shows a window with both edges and gaps", () => {
    assert.deepEqual(paginationWindow(6, 17), [1, E, 4, 5, 6, 7, 8, E, 17]);
  });

  it("has no leading gap near the start", () => {
    assert.deepEqual(paginationWindow(1, 17), [1, 2, 3, E, 17]);
    assert.deepEqual(paginationWindow(3, 17), [1, 2, 3, 4, 5, E, 17]);
  });

  it("shows a single skipped page instead of an ellipsis", () => {
    assert.deepEqual(paginationWindow(5, 17), [1, 2, 3, 4, 5, 6, 7, E, 17]);
    assert.deepEqual(paginationWindow(13, 17), [
      1,
      E,
      11,
      12,
      13,
      14,
      15,
      16,
      17,
    ]);
  });

  it("has no trailing gap near the end", () => {
    assert.deepEqual(paginationWindow(17, 17), [1, E, 15, 16, 17]);
    assert.deepEqual(paginationWindow(15, 17), [1, E, 13, 14, 15, 16, 17]);
  });

  it("lists every page when they fit", () => {
    assert.deepEqual(paginationWindow(1, 1), [1]);
    assert.deepEqual(paginationWindow(2, 2), [1, 2]);
    assert.deepEqual(paginationWindow(4, 7), [1, 2, 3, 4, 5, 6, 7]);
  });

  it("clamps out-of-range input", () => {
    assert.deepEqual(paginationWindow(0, 0), []);
    assert.deepEqual(paginationWindow(99, 17), [1, E, 15, 16, 17]);
    assert.deepEqual(paginationWindow(-3, 17), [1, 2, 3, E, 17]);
    assert.deepEqual(paginationWindow(Number.NaN, 5), [1, 2, 3, 4, 5]);
  });

  it("respects the sibling count", () => {
    assert.deepEqual(paginationWindow(6, 17, 1), [1, E, 5, 6, 7, E, 17]);
  });
});

describe("pageHref", () => {
  it("uses the bare path for page 1", () => {
    assert.equal(pageHref("/", 1), "/");
    assert.equal(pageHref("/tag/go", 1), "/tag/go");
    assert.equal(pageHref("/category/web3", 3), "/category/web3?page=3");
  });
});

describe("parsePageParam", () => {
  it("accepts canonical page numbers", () => {
    assert.deepEqual(parsePageParam(null), { page: 1, redirect: false });
    assert.deepEqual(parsePageParam("6"), { page: 6, redirect: false });
  });

  it("redirects non-canonical spellings", () => {
    assert.deepEqual(parsePageParam("1"), { page: 1, redirect: true });
    assert.deepEqual(parsePageParam("06"), { page: 6, redirect: true });
    assert.deepEqual(parsePageParam("0"), { page: 1, redirect: true });
    assert.deepEqual(parsePageParam(""), { page: 1, redirect: true });
    assert.deepEqual(parsePageParam("abc"), { page: 1, redirect: true });
    assert.deepEqual(parsePageParam("-2"), { page: 1, redirect: true });
    assert.deepEqual(parsePageParam("99999999999"), {
      page: 1,
      redirect: true,
    });
  });
});

describe("pageRedirectLocation", () => {
  it("keeps other query parameters", () => {
    assert.equal(
      pageRedirectLocation("/", new URLSearchParams("page=1&utm_source=x"), 1),
      "/?utm_source=x"
    );
    assert.equal(
      pageRedirectLocation("/tag/go", new URLSearchParams("page=abc"), 1),
      "/tag/go"
    );
  });

  it("never produces a protocol-relative location", () => {
    assert.equal(
      pageRedirectLocation("//evil.example/", new URLSearchParams("page=1"), 1),
      "/evil.example/"
    );
    assert.equal(
      pageRedirectLocation("/\\evil.example", new URLSearchParams(), 1),
      "/evil.example"
    );
  });
});

describe("isPageOutOfRange", () => {
  it("flags pages beyond the last one but not page 1", () => {
    assert.equal(isPageOutOfRange(18, 17), true);
    assert.equal(isPageOutOfRange(17, 17), false);
    assert.equal(isPageOutOfRange(1, 0), false);
  });
});
