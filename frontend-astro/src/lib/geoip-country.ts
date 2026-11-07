import { stat } from "node:fs/promises";
import { open, type CountryResponse, type Reader } from "maxmind";
import { trustedClientIp } from "./language-choice";
import { isGeoIpLocaleRoutingEnabled } from "./geoip-config";

const databasePath = process.env.GEOIP_COUNTRY_DB ?? "/geoip/dbip-country-lite.mmdb";
let reader: Reader<CountryResponse> | null = null;
let readerMtime = -1;
let opening: Promise<Reader<CountryResponse>> | null = null;
let retryAfter = 0;
let warned = false;

async function getReader(): Promise<Reader<CountryResponse> | null> {
  if (Date.now() < retryAfter) return null;

  try {
    const info = await stat(databasePath);
    if (reader && info.mtimeMs === readerMtime) return reader;
    if (opening && info.mtimeMs === readerMtime) return opening;

    readerMtime = info.mtimeMs;
    opening = open<CountryResponse>(databasePath);
    reader = await opening;
    retryAfter = 0;
    return reader;
  } catch {
    reader = null;
    opening = null;
    readerMtime = -1;
    retryAfter = Date.now() + 60_000;
    if (!warned) {
      console.warn("[geoip] Country database unavailable; using the unknown-country default.");
      warned = true;
    }
    return null;
  }
}

/** A local MMDB lookup only; it makes no request to a geolocation service. */
export async function countryForRequest(headers: Pick<Headers, "get">): Promise<string | null> {
  if (!isGeoIpLocaleRoutingEnabled()) return null;
  const ip = trustedClientIp(headers);
  if (!ip) return null;

  const database = await getReader();
  if (!database) return null;

  try {
    const code = database.get(ip)?.country?.iso_code?.toUpperCase();
    return code && /^[A-Z]{2}$/.test(code) ? code : null;
  } catch {
    return null;
  }
}
