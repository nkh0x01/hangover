import { readFile, appendFile } from "node:fs/promises";

const options = parseArgs(process.argv.slice(2));
const summaryPath = process.env.GITHUB_STEP_SUMMARY;

if (!summaryPath) {
  process.exit(0);
}

const build = await readJson(options["build-json"]);
const submit = await readJson(options["submit-json"]);
const buildRecord = Array.isArray(build) ? build[0] : build;
const submitRecord = Array.isArray(submit) ? submit[0] : submit;

const appLabel = options.app === "driver" ? "Driver" : "Customer";
const uploadStatus = options["submit-enabled"] === "true"
  ? statusFrom(submitRecord) ?? "submitted/requested"
  : "skipped";

const markdown = [
  `## Ride 360 ${appLabel} Expo V2 iOS`,
  "",
  `- GitHub run: ${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`,
  `- EAS build ID: ${buildRecord?.id ?? "unknown"}`,
  `- EAS build URL: ${buildUrl(buildRecord)}`,
  `- Bundle ID: ${options["bundle-id"]}`,
  `- Version/build: ${options.version}/${options["build-number"]}`,
  "- Xcode SDK: EAS iOS image `latest`; exact Xcode/iPhoneOS SDK is recorded in the EAS build log.",
  `- IPA path/artifact: ${options["ipa-path"] || "not downloaded"}`,
  `- TestFlight upload status: ${uploadStatus}`,
  "",
].join("\n");

await appendFile(summaryPath, markdown);

function parseArgs(args) {
  const parsed = {};
  for (let index = 0; index < args.length; index += 2) {
    const key = args[index]?.replace(/^--/, "");
    const value = args[index + 1];
    if (key) parsed[key] = value;
  }
  return parsed;
}

async function readJson(filePath) {
  if (!filePath) return null;
  try {
    return JSON.parse(await readFile(filePath, "utf8"));
  } catch {
    return null;
  }
}

function buildUrl(buildRecord) {
  if (buildRecord?.buildDetailsPageUrl) return buildRecord.buildDetailsPageUrl;
  if (buildRecord?.dashboardUrl) return buildRecord.dashboardUrl;
  if (buildRecord?.id) return `https://expo.dev/builds/${buildRecord.id}`;
  return "unknown";
}

function statusFrom(record) {
  return record?.status ?? record?.submissionStatus ?? record?.state ?? null;
}
