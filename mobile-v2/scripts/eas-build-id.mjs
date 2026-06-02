import { readFile } from "node:fs/promises";

const [jsonPath] = process.argv.slice(2);

if (!jsonPath) {
  console.error("Usage: node scripts/eas-build-id.mjs <eas-build-json>");
  process.exit(1);
}

const parsed = JSON.parse(await readFile(jsonPath, "utf8"));
const build = Array.isArray(parsed) ? parsed[0] : parsed;
const buildId = build?.id ?? build?.buildId;

if (!buildId) {
  console.error(`Could not read build id from ${jsonPath}.`);
  process.exit(1);
}

console.log(buildId);
