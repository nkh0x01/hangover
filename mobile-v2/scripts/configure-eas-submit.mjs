import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";

const [
  profile,
  ascAppId,
  appleTeamId,
  ascApiKeyPath,
  ascApiKeyId,
  ascApiKeyIssuerId,
] = process.argv.slice(2);

if (!profile) {
  console.error(
    "Usage: node scripts/configure-eas-submit.mjs <profile> [ascAppId] [appleTeamId] [ascApiKeyPath] [ascApiKeyId] [ascApiKeyIssuerId]",
  );
  process.exit(1);
}

if (
  !ascAppId &&
  !(ascApiKeyPath && ascApiKeyId && ascApiKeyIssuerId)
) {
  console.error(
    `Missing App Store Connect app ID or API key fields for submit profile ${profile}.`,
  );
  process.exit(1);
}

const easJsonPath = path.resolve(new URL("..", import.meta.url).pathname, "eas.json");
const easJson = JSON.parse(await readFile(easJsonPath, "utf8"));

easJson.submit ??= {};
easJson.submit[profile] ??= {};
easJson.submit[profile].ios ??= {};

if (ascAppId) {
  easJson.submit[profile].ios.ascAppId = ascAppId;
}

if (appleTeamId) {
  easJson.submit[profile].ios.appleTeamId = appleTeamId;
}

if (ascApiKeyPath && ascApiKeyId && ascApiKeyIssuerId) {
  easJson.submit[profile].ios.ascApiKeyPath = ascApiKeyPath;
  easJson.submit[profile].ios.ascApiKeyId = ascApiKeyId;
  easJson.submit[profile].ios.ascApiKeyIssuerId = ascApiKeyIssuerId;
}

await writeFile(easJsonPath, `${JSON.stringify(easJson, null, 2)}\n`);
console.log(`Configured iOS submit profile ${profile}.`);
