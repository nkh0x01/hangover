import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";

const [profile, ascAppId, appleTeamId] = process.argv.slice(2);

if (!profile) {
  console.error("Usage: node scripts/configure-eas-submit.mjs <profile> <ascAppId> [appleTeamId]");
  process.exit(1);
}

if (!ascAppId) {
  console.error(`Missing App Store Connect app ID for submit profile ${profile}.`);
  process.exit(1);
}

const easJsonPath = path.resolve(new URL("..", import.meta.url).pathname, "eas.json");
const easJson = JSON.parse(await readFile(easJsonPath, "utf8"));

easJson.submit ??= {};
easJson.submit[profile] ??= {};
easJson.submit[profile].ios ??= {};
easJson.submit[profile].ios.ascAppId = ascAppId;

if (appleTeamId) {
  easJson.submit[profile].ios.appleTeamId = appleTeamId;
}

await writeFile(easJsonPath, `${JSON.stringify(easJson, null, 2)}\n`);
console.log(`Configured iOS submit profile ${profile}.`);
