const { execSync } = require("node:child_process");

const apps = {
  customer: {
    role: "customer",
    name: "Ride 360 Customer V2",
    slug: "ride360-customer-v2",
    scheme: "ride360-customer-v2",
    iosBundleIdentifier: "app.ride360.customer",
    easProjectIdEnv: "EXPO_CUSTOMER_EAS_PROJECT_ID",
  },
  driver: {
    role: "driver",
    name: "Ride 360 Driver V2",
    slug: "ride360-driver-v2",
    scheme: "ride360-driver-v2",
    icon: "./apps/driver/assets/icon.png",
    iosBundleIdentifier: "app.ride360.driver",
    easProjectIdEnv: "EXPO_DRIVER_EAS_PROJECT_ID",
    iosInfoPlist: {
      NSLocationWhenInUseUsageDescription:
        "Ride 360 Driver V2 uses your location to start shifts and receive nearby ride offers.",
    },
  },
};

const target = process.env.EXPO_APP_TARGET ?? "customer";
const app = apps[target];

if (!app) {
  throw new Error(`Unsupported EXPO_APP_TARGET "${target}". Use customer or driver.`);
}

const apiBaseUrl =
  process.env.EXPO_PUBLIC_API_BASE_URL ?? "https://ride.365sakartvelo.com";
const appEnv = process.env.EXPO_PUBLIC_APP_ENV ?? "development";
const appName = process.env.EXPO_PUBLIC_APP_NAME ?? app.name;
const appVersion = process.env.EXPO_PUBLIC_APP_VERSION ?? "0.1.0";
const iosBuildNumber = process.env.IOS_BUILD_NUMBER ?? "200000";
const easProjectId =
  process.env.EXPO_EAS_PROJECT_ID ?? process.env[app.easProjectIdEnv];
const explicitDriverDiagnosticMode =
  target === "driver" ? process.env.EXPO_DRIVER_DIAGNOSTIC_MODE : undefined;
const legacyDriverDiagnosticMode =
  target === "driver" && process.env.EXPO_DRIVER_PRIMITIVE_BOOT === "true"
    ? "primitive"
    : target === "driver" && process.env.EXPO_DRIVER_NULL_BOOT === "true"
      ? "null"
      : target === "driver" && process.env.EXPO_DRIVER_DIAGNOSTIC_BOOT === "true"
        ? "staged"
        : target === "driver" && process.env.EXPO_DRIVER_BOOT_ONLY === "true"
          ? "cleanroom"
          : undefined;
const driverDiagnosticMode =
  target === "driver"
    ? explicitDriverDiagnosticMode || legacyDriverDiagnosticMode || "full"
    : "full";
const driverEntryPoints = {
  full: "./index.js",
  cleanroom: "./apps/driver/boot-only-index.js",
  null: "./apps/driver/null-render-index.js",
  primitive: "./apps/driver/primitive-ui-index.js",
  staged: "./apps/driver/diagnostic-index.js",
};
if (!driverEntryPoints[driverDiagnosticMode]) {
  throw new Error(
    `Unsupported EXPO_DRIVER_DIAGNOSTIC_MODE "${driverDiagnosticMode}". Use full, cleanroom, null, primitive, or staged.`,
  );
}
const driverJsEngine =
  target === "driver" && process.env.EXPO_DRIVER_JS_ENGINE === "jsc"
    ? "jsc"
    : undefined;
const gitCommit = process.env.GIT_COMMIT ?? readGitCommit();
const entryPoint =
  target === "driver" ? driverEntryPoints[driverDiagnosticMode] : "./index.js";

module.exports = {
  expo: {
    name: appName,
    slug: app.slug,
    scheme: app.scheme,
    version: appVersion,
    orientation: "portrait",
    userInterfaceStyle: "automatic",
    newArchEnabled: false,
    ...(driverJsEngine ? { jsEngine: driverJsEngine } : {}),
    ...(app.icon ? { icon: app.icon } : {}),
    entryPoint,
    runtimeVersion: {
      policy: "appVersion",
    },
    ios: {
      bundleIdentifier: app.iosBundleIdentifier,
      buildNumber: iosBuildNumber,
      supportsTablet: false,
      ...(driverJsEngine ? { jsEngine: driverJsEngine } : {}),
      ...(app.icon ? { icon: app.icon } : {}),
      infoPlist: {
        ITSAppUsesNonExemptEncryption: false,
        ...(app.iosInfoPlist ?? {}),
      },
    },
    extra: {
      appRole: app.role,
      apiBaseUrl,
      appEnv,
      gitCommit,
      driverDiagnosticMode,
      driverJsEngine,
      eas: easProjectId ? { projectId: easProjectId } : undefined,
    },
  },
};

function readGitCommit() {
  try {
    return execSync("git rev-parse --short HEAD", {
      cwd: __dirname,
      stdio: ["ignore", "pipe", "ignore"],
      encoding: "utf8",
    }).trim();
  } catch {
    return "unknown";
  }
}
