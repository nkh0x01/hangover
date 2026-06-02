import { readFile } from "node:fs/promises";
import crypto from "node:crypto";

const [bundleId, apiKeyPath, apiKeyId, issuerId] = process.argv.slice(2);

if (!(bundleId && apiKeyPath && apiKeyId && issuerId)) {
  console.error(
    "Usage: node scripts/resolve-asc-app-id.mjs <bundleId> <apiKeyPath> <apiKeyId> <issuerId>",
  );
  process.exit(1);
}

const privateKey = await readFile(apiKeyPath, "utf8");
const token = createJwt({
  apiKeyId,
  issuerId,
  privateKey,
});

const url = new URL("https://api.appstoreconnect.apple.com/v1/apps");
url.searchParams.set("filter[bundleId]", bundleId);
url.searchParams.set("limit", "1");

const response = await fetch(url, {
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  },
});

if (!response.ok) {
  const excerpt = (await response.text()).slice(0, 500);
  console.error(
    `Could not resolve App Store Connect app id for ${bundleId}: HTTP ${response.status} ${excerpt}`,
  );
  process.exit(1);
}

const payload = await response.json();
const appId = payload?.data?.[0]?.id;

if (!appId) {
  console.error(`No App Store Connect app found for bundle id ${bundleId}.`);
  process.exit(1);
}

console.log(appId);

function createJwt({ apiKeyId, issuerId, privateKey }) {
  const now = Math.floor(Date.now() / 1000);
  const header = {
    alg: "ES256",
    kid: apiKeyId,
    typ: "JWT",
  };
  const claims = {
    iss: issuerId,
    iat: now - 60,
    exp: now + 20 * 60,
    aud: "appstoreconnect-v1",
  };

  const signingInput = `${base64UrlJson(header)}.${base64UrlJson(claims)}`;
  const signature = crypto.sign("sha256", Buffer.from(signingInput), {
    key: privateKey,
    dsaEncoding: "ieee-p1363",
  });

  return `${signingInput}.${base64Url(signature)}`;
}

function base64UrlJson(value) {
  return base64Url(Buffer.from(JSON.stringify(value)));
}

function base64Url(value) {
  return Buffer.from(value)
    .toString("base64")
    .replaceAll("+", "-")
    .replaceAll("/", "_")
    .replaceAll("=", "");
}
