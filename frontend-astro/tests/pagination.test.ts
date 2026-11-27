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
    assert.deepEqual(parsePageParam(null), { page: 1, redirect: false, notFound: false });
    assert.deepEqual(parsePageParam("6"), { page: 6, redirect: false, notFound: false });
  });

  it("redirects non-canonical spellings", () => {
    assert.deepEqual(parsePageParam("1"), { page: 1, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam("06"), { page: 6, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam("0"), { page: 1, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam(""), { page: 1, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam("abc"), { page: 1, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam("-2"), { page: 1, redirect: true, notFound: false });
    assert.deepEqual(parsePageParam("99999999999"), {
      page: 1,
      redirect: false,
      notFound: true,
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

describe("paginationWindow invariants", () => {
  it("holds for every total, current and sibling count", () => {
    for (let total = 1; total <= 20; total++) {
      for (let current = 1; current <= total; current++) {
        for (let siblings = 0; siblings <= 3; siblings++) {
          const items = paginationWindow(current, total, siblings);
          const nums = items.filter((i): i is number => i !== E);
          assert.equal(nums[0], 1);
          assert.equal(nums.at(-1), total);
          assert.ok(nums.includes(current));
          assert.deepEqual(
            nums,
            [...new Set(nums)].sort((a, b) => a - b)
          );
          items.forEach((item, i) => {
            if (item !== E) return;
            assert.equal(typeof items[i - 1], "number");
            assert.equal(typeof items[i + 1], "number");
            const gap = (items[i + 1] as number) - (items[i - 1] as number) - 1;
            assert.ok(gap >= 2);
          });
        }
      }
    }
  });
});

describe("parsePageParam edge values", () => {
  it("treats very long numbers as not found, not as a redirect", () => {
    assert.equal(parsePageParam("999999999").notFound, false);
    assert.equal(parsePageParam("1000000000").notFound, true);
    assert.equal(parsePageParam("1000000000").redirect, false);
  });

  it("redirects non-canonical spellings", () => {
    for (const raw of ["+2", " 2", "02", "1", "0", "abc", ""]) {
      assert.equal(parsePageParam(raw).redirect, true, raw);
    }
    assert.equal(parsePageParam("02").page, 2);
  });
});

describe("pageRedirectLocation with page above 1", () => {
  it("normalises the page and keeps other parameters", () => {
    assert.equal(
      pageRedirectLocation("/", new URLSearchParams("utm=x&page=02"), 2),
      "/?utm=x&page=2"
    );
  });
});
