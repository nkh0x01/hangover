import { createRequire } from "node:module";
import { readFile, readdir } from "node:fs/promises";
import path from "node:path";

const require = createRequire(import.meta.url);
const root = path.resolve(new URL("..", import.meta.url).pathname);
const productionApiBaseUrl = "https://ride.365sakartvelo.com";

const expectedApps = {
  customer: {
    name: "Ride 360 Customer V2",
    role: "customer",
    slug: "ride360-customer-v2",
    bundleIdentifier: "app.ride360.customer",
  },
  driver: {
    name: "Ride 360 Driver V2",
    role: "driver",
    slug: "ride360-driver-v2",
    bundleIdentifier: "app.ride360.driver",
    locationUsageDescription:
      "Ride 360 Driver V2 uses your location to start shifts and receive nearby ride offers.",
  },
};

const previousEnv = { ...process.env };

for (const [target, expected] of Object.entries(expectedApps)) {
  process.env = {
    ...previousEnv,
    EXPO_APP_TARGET: target,
    EXPO_PUBLIC_APP_NAME: expected.name,
    EXPO_PUBLIC_APP_ROLE: expected.role,
    EXPO_PUBLIC_APP_ENV: "production",
    EXPO_PUBLIC_API_BASE_URL: productionApiBaseUrl,
    EXPO_PUBLIC_APP_VERSION: "2.0.0",
    IOS_BUILD_NUMBER: "200000",
    EXPO_EAS_PROJECT_ID: "00000000-0000-4000-8000-000000000000",
  };

  delete require.cache[require.resolve("../app.config.js")];
  const config = require("../app.config.js").expo;

  assertEqual(config.name, expected.name, `${target} name`);
  assertEqual(config.slug, expected.slug, `${target} slug`);
  assertEqual(config.version, "2.0.0", `${target} version`);
  assertEqual(config.ios.bundleIdentifier, expected.bundleIdentifier, `${target} bundle ID`);
  assertEqual(config.ios.buildNumber, "200000", `${target} build number`);
  assertEqual(config.extra.appRole, expected.role, `${target} app role`);
  assertEqual(config.extra.appEnv, "production", `${target} app env`);
  assertEqual(config.extra.apiBaseUrl, productionApiBaseUrl, `${target} API base URL`);
  if (expected.locationUsageDescription) {
    assertEqual(
      config.ios.infoPlist.NSLocationWhenInUseUsageDescription,
      expected.locationUsageDescription,
      `${target} location usage description`,
    );
  }
  assertEqual(
    config.extra.eas.projectId,
    "00000000-0000-4000-8000-000000000000",
    `${target} EAS project ID passthrough`,
  );
}

process.env = previousEnv;

const configSource = await readFile(
  path.join(root, "packages/config/src/index.ts"),
  "utf8",
);
if (!configSource.includes(`const DEFAULT_API_BASE_URL = "${productionApiBaseUrl}"`)) {
  fail("packages/config default API base URL is not production.");
}

const entrySource = await readFile(path.join(root, "index.js"), "utf8");
if (!entrySource.includes("EXPO_PUBLIC_APP_ROLE")) {
  fail("root entrypoint must select the app using EXPO_PUBLIC_APP_ROLE.");
}
if (entrySource.includes("EXPO_APP_TARGET")) {
  fail("root entrypoint must not select the app using non-public EXPO_APP_TARGET.");
}

await scanForDevUrls();
console.log("Expo V2 production config checks passed.");

function assertEqual(actual, expected, label) {
  if (actual !== expected) {
    fail(`${label}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
  }
}

function fail(message) {
  console.error(`Production config check failed: ${message}`);
  process.exit(1);
}

async function scanForDevUrls() {
  const sourceRoots = [
    path.join(root, "app.config.js"),
    path.join(root, "index.js"),
    path.join(root, "eas.json"),
    path.join(root, "apps"),
    path.join(root, "packages"),
  ];

  const forbiddenPatterns = [
    /http:\/\/localhost/i,
    /http:\/\/127\.0\.0\.1/i,
    /http:\/\/0\.0\.0\.0/i,
    /ngrok/i,
    /ride360-dev/i,
  ];

  const allowedFiles = new Set([
    path.join(root, "apps/customer/src/customer-flow.test.ts"),
    path.join(root, "apps/driver/src/driver-flow.test.ts"),
    path.join(root, "packages/api/src/api.test.ts"),
  ]);

  for (const sourceRoot of sourceRoots) {
    for await (const file of walk(sourceRoot)) {
      if (allowedFiles.has(file)) continue;
      if (!/\.(js|json|ts|tsx)$/.test(file)) continue;

      const contents = await readFile(file, "utf8");
      for (const pattern of forbiddenPatterns) {
        if (pattern.test(contents)) {
          fail(`dev URL/string matched ${pattern} in ${path.relative(root, file)}`);
        }
      }
    }
  }
}

async function* walk(fileOrDirectory) {
  const entries = await readdir(fileOrDirectory, { withFileTypes: true }).catch(() => null);
  if (!entries) {
    yield fileOrDirectory;
    return;
  }

  for (const entry of entries) {
    if (entry.name === "node_modules" || entry.name === ".expo") continue;
    const fullPath = path.join(fileOrDirectory, entry.name);
    if (entry.isDirectory()) {
      yield* walk(fullPath);
    } else {
      yield fullPath;
    }
  }
}
