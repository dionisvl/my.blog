import { createHash } from "node:crypto";
import { mkdir, readFile, rename, rm, writeFile } from "node:fs/promises";
import { gunzip } from "node:zlib";
import { promisify } from "node:util";
import { join } from "node:path";

const gunzipAsync = promisify(gunzip);
const sourcePage = "https://db-ip.com/db/download/ip-to-country-lite";
const dbDirectory = process.env.GEOIP_DB_DIR ?? "/geoip";
const dbFile = join(dbDirectory, "dbip-country-lite.mmdb");
const manifestFile = join(dbDirectory, "dbip-country-lite.json");
const pollIntervalMs = 24 * 60 * 60 * 1000;
const runOnce = process.argv.includes("--once");

function parseRelease(html) {
  const cards = /<dl class=["']card-body["']>([\s\S]*?)<\/dl>/gi;
  for (const match of html.matchAll(cards)) {
    const details = match[1];
    if (!/<dd>\s*MMDB\s*<\/dd>/i.test(details)) continue;
    const afterCard = html.slice(match.index + match[0].length, match.index + match[0].length + 1500);
    const url = afterCard.match(/<a\b[^>]*\bhref=["'](https:\/\/download\.db-ip\.com\/free\/dbip-country-lite-\d{4}-\d{2}\.mmdb\.gz)["']/i)?.[1];
    const checksum = details.match(/<dt>\s*SHA1SUM\s*<\/dt>\s*<dd[^>]*>\s*([a-f0-9]{40})\s*<\/dd>/i)?.[1];
    const release = url?.match(/dbip-country-lite-(\d{4}-\d{2})\.mmdb\.gz$/)?.[1];
    if (checksum && release && url) return { release, checksum: checksum.toLowerCase(), url };
  }
  throw new Error("Could not identify the official DB-IP Lite MMDB release and SHA1 checksum");
}

async function fetchText(url) {
  const response = await fetch(url, { signal: AbortSignal.timeout(30_000) });
  if (!response.ok) throw new Error(`Download page returned HTTP ${response.status}`);
  if (new URL(response.url).hostname !== new URL(sourcePage).hostname) throw new Error("Unexpected download-page host");
  return response.text();
}

async function updateOnce() {
  await mkdir(dbDirectory, { recursive: true });
  const release = parseRelease(await fetchText(sourcePage));

  try {
    const manifest = JSON.parse(await readFile(manifestFile, "utf8"));
    if (manifest.release === release.release && manifest.sha1 === release.checksum) {
      const installed = await readFile(dbFile);
      if (createHash("sha1").update(installed).digest("hex") === release.checksum) {
        console.log(`[geoip] ${release.release} already installed and verified`);
        return;
      }
    }
  } catch {
    // Missing or incomplete state triggers a verified download; it never deletes the current DB.
  }

  const response = await fetch(release.url, { signal: AbortSignal.timeout(60_000) });
  if (!response.ok) throw new Error(`Database download returned HTTP ${response.status}`);
  if (new URL(response.url).hostname !== "download.db-ip.com") throw new Error("Unexpected database host");
  const compressed = Buffer.from(await response.arrayBuffer());
  if (compressed.length === 0 || compressed.length > 32 * 1024 * 1024) throw new Error("Unexpected compressed database size");

  const database = await gunzipAsync(compressed, { maxOutputLength: 128 * 1024 * 1024 });
  const actualSha1 = createHash("sha1").update(database).digest("hex");
  if (actualSha1 !== release.checksum) throw new Error("Downloaded database checksum does not match DB-IP's published SHA1");
  if (database.length < 1_000_000 || !database.includes(Buffer.from("MaxMind.com"))) {
    throw new Error("Downloaded file does not look like a valid MMDB database");
  }

  const suffix = `${process.pid}-${Date.now()}`;
  const tempDb = `${dbFile}.tmp-${suffix}`;
  const tempManifest = `${manifestFile}.tmp-${suffix}`;
  try {
    await writeFile(tempDb, database, { flag: "wx", mode: 0o644 });
    await rename(tempDb, dbFile);
    await writeFile(tempManifest, `${JSON.stringify({ release: release.release, sha1: actualSha1 }, null, 2)}\n`, { flag: "wx", mode: 0o644 });
    await rename(tempManifest, manifestFile);
  } finally {
    await rm(tempDb, { force: true });
    await rm(tempManifest, { force: true });
  }
  console.log(`[geoip] installed DB-IP Lite MMDB ${release.release}`);
}

for (;;) {
  try {
    await updateOnce();
  } catch (error) {
    console.error(`[geoip] update did not complete; no checksum-invalid database is installed: ${error instanceof Error ? error.message : String(error)}`);
    if (runOnce) process.exitCode = 1;
  }
  if (runOnce) break;
  await new Promise((resolve) => setTimeout(resolve, pollIntervalMs));
}
