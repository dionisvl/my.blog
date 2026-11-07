import assert from "node:assert/strict";
import { describe, it } from "node:test";

import { isGeoIpLocaleRoutingEnabled } from "../src/lib/geoip-config.ts";

describe("deferred Geo-IP routing flag", () => {
  it("is opt-in and requires the exact true value", () => {
    assert.equal(isGeoIpLocaleRoutingEnabled(""), false);
    assert.equal(isGeoIpLocaleRoutingEnabled("false"), false);
    assert.equal(isGeoIpLocaleRoutingEnabled("TRUE"), false);
    assert.equal(isGeoIpLocaleRoutingEnabled("true"), true);
  });

  it("defaults to disabled when the environment variable is unset", () => {
    const previous = process.env.GEOIP_LOCALE_ROUTING_ENABLED;
    delete process.env.GEOIP_LOCALE_ROUTING_ENABLED;
    try {
      assert.equal(isGeoIpLocaleRoutingEnabled(), false);
    } finally {
      if (previous === undefined) delete process.env.GEOIP_LOCALE_ROUTING_ENABLED;
      else process.env.GEOIP_LOCALE_ROUTING_ENABLED = previous;
    }
  });
});
