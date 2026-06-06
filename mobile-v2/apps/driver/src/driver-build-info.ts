import type { AppEnv } from "@ride360/config";

export type DriverBuildInfo = {
  appEnv: AppEnv;
  buildNumber?: string;
  googleMapsConfigured: boolean;
  iosBundleIdentifier?: string;
  mapsKeyLength: number;
  mapsKeySha256Prefix?: string;
  mapProvider: "apple" | "google";
  version: string;
};

export type DriverBuildInfoInput = {
  extra?: Record<string, unknown>;
  publicAppEnv?: string;
  publicBuildNumber?: string;
  publicGoogleMapsEnabled?: string;
  publicMapProvider?: string;
  publicVersion?: string;
  runtimeAppEnv?: AppEnv;
};

export function createDriverBuildInfo(input: DriverBuildInfoInput): DriverBuildInfo {
  const extra = input.extra ?? {};
  const version =
    cleanString(input.publicVersion) ??
    cleanString(extra.appVersion) ??
    "0.1.0";
  const buildNumber =
    cleanString(input.publicBuildNumber) ??
    cleanString(extra.appBuildNumber) ??
    undefined;
  const appEnv = parseAppEnv(
    cleanString(input.publicAppEnv) ??
      cleanString(extra.appEnv) ??
      input.runtimeAppEnv,
  );
  const mapProvider = parseMapProvider(
    cleanString(input.publicMapProvider) ?? cleanString(extra.mapProvider),
  );
  const iosBundleIdentifier = cleanString(extra.iosBundleIdentifier);
  const mapsKeyLength = parseKeyLength(extra.mapsKeyLength);
  const mapsKeySha256Prefix = cleanString(extra.mapsKeySha256Prefix);
  const googleMapsConfigured =
    input.publicGoogleMapsEnabled === "true" ||
    extra.googleMapsConfigured === true;

  return {
    appEnv,
    buildNumber,
    googleMapsConfigured,
    iosBundleIdentifier,
    mapsKeyLength,
    mapsKeySha256Prefix,
    mapProvider,
    version,
  };
}

export function buildLabelText(info: DriverBuildInfo): string {
  const build = info.buildNumber ? ` (${info.buildNumber})` : "";
  return `Driver V2 · ${info.version}${build} · ${info.appEnv}`;
}

export function mapProviderLabel(info: DriverBuildInfo): string {
  if (info.mapProvider === "google" && info.googleMapsConfigured) return "Google";
  return info.mapProvider === "google" ? "Google (key missing)" : "Apple";
}

function cleanString(value: unknown): string | undefined {
  if (typeof value !== "string") return undefined;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : undefined;
}

function parseAppEnv(value: string | undefined): AppEnv {
  if (value === "production" || value === "staging") return value;
  return "development";
}

function parseMapProvider(value: string | undefined): DriverBuildInfo["mapProvider"] {
  return value === "google" ? "google" : "apple";
}

function parseKeyLength(value: unknown): number {
  return typeof value === "number" && Number.isFinite(value) ? value : 0;
}
