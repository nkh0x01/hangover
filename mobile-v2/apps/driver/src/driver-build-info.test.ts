import { describe, expect, it } from "vitest";

import {
  buildLabelText,
  createDriverBuildInfo,
  mapProviderLabel,
} from "./driver-build-info";

describe("driver build info", () => {
  it("build label uses correct production version, build, and env", () => {
    const info = createDriverBuildInfo({
      publicAppEnv: "production",
      publicBuildNumber: "200025",
      publicVersion: "2.0.0",
    });

    expect(buildLabelText(info)).toBe("Driver V2 · 2.0.0 (200025) · production");
  });

  it("falls back to Expo config extra when public env is unavailable", () => {
    const info = createDriverBuildInfo({
      extra: {
        appBuildNumber: "200024",
        appEnv: "production",
        appVersion: "2.0.0",
        googleMapsConfigured: true,
        iosBundleIdentifier: "app.ride360.driver",
        mapsKeyLength: 39,
        mapsKeySha256Prefix: "9242c62ce14f",
        mapProvider: "google",
      },
    });

    expect(buildLabelText(info)).toBe("Driver V2 · 2.0.0 (200024) · production");
    expect(info.iosBundleIdentifier).toBe("app.ride360.driver");
    expect(info.mapsKeyLength).toBe(39);
    expect(info.mapsKeySha256Prefix).toBe("9242c62ce14f");
    expect(mapProviderLabel(info)).toBe("Google");
  });

  it("defaults Driver iOS maps to Apple/default without requiring a Google key", () => {
    const info = createDriverBuildInfo({
      extra: {
        appEnv: "production",
        mapProvider: "apple",
      },
    });

    expect(info.googleMapsConfigured).toBe(false);
    expect(info.mapProvider).toBe("apple");
    expect(info.mapsKeyLength).toBe(0);
    expect(mapProviderLabel(info)).toBe("Apple/default");
  });

  it("does not report Google Maps key present just because provider is google", () => {
    const info = createDriverBuildInfo({
      extra: {
        mapProvider: "google",
      },
    });

    expect(info.googleMapsConfigured).toBe(false);
    expect(mapProviderLabel(info)).toBe("Google (key missing)");
  });
});
