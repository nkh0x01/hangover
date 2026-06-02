export type AppEnv = "development" | "staging" | "production";
export type AppRole = "customer" | "driver";

export type RuntimeConfig = {
  API_BASE_URL: string;
  APP_ENV: AppEnv;
  APP_NAME: string;
  APP_ROLE: AppRole;
};

const DEFAULT_API_BASE_URL = "https://ride.365sakartvelo.com";
const DEFAULT_APP_ENV: AppEnv = "development";

function readPublicEnv(key: string): string | undefined {
  const env = globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
  };

  return env.process?.env?.[key];
}

function parseAppEnv(value: string | undefined): AppEnv {
  if (value === "staging" || value === "production") return value;
  return DEFAULT_APP_ENV;
}

function parseAppRole(value: string | undefined, fallback: AppRole): AppRole {
  if (value === "driver" || value === "customer") return value;
  return fallback;
}

export function createRuntimeConfig(input: {
  appName: string;
  appRole: AppRole;
  apiBaseUrl?: string;
  appEnv?: AppEnv;
}): RuntimeConfig {
  return {
    API_BASE_URL:
      input.apiBaseUrl ??
      readPublicEnv("EXPO_PUBLIC_API_BASE_URL") ??
      DEFAULT_API_BASE_URL,
    APP_ENV: input.appEnv ?? parseAppEnv(readPublicEnv("EXPO_PUBLIC_APP_ENV")),
    APP_NAME: input.appName,
    APP_ROLE: parseAppRole(readPublicEnv("EXPO_PUBLIC_APP_ROLE"), input.appRole),
  };
}

export const API_BASE_URL =
  readPublicEnv("EXPO_PUBLIC_API_BASE_URL") ?? DEFAULT_API_BASE_URL;
export const APP_ENV = parseAppEnv(readPublicEnv("EXPO_PUBLIC_APP_ENV"));
export const APP_NAME = readPublicEnv("EXPO_PUBLIC_APP_NAME") ?? "Ride 360 V2";
export const APP_ROLE = parseAppRole(
  readPublicEnv("EXPO_PUBLIC_APP_ROLE"),
  "customer",
);
