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
    iosBundleIdentifier: "app.ride360.driver",
    easProjectIdEnv: "EXPO_DRIVER_EAS_PROJECT_ID",
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

module.exports = {
  expo: {
    name: appName,
    slug: app.slug,
    scheme: app.scheme,
    version: appVersion,
    orientation: "portrait",
    userInterfaceStyle: "automatic",
    newArchEnabled: true,
    entryPoint: "./index.js",
    runtimeVersion: {
      policy: "appVersion",
    },
    ios: {
      bundleIdentifier: app.iosBundleIdentifier,
      buildNumber: iosBuildNumber,
      supportsTablet: false,
      infoPlist: {
        ITSAppUsesNonExemptEncryption: false,
      },
    },
    extra: {
      appRole: app.role,
      apiBaseUrl,
      appEnv,
      eas: easProjectId ? { projectId: easProjectId } : undefined,
    },
  },
};
