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
        mapProvider: "google",
      },
    });

    expect(buildLabelText(info)).toBe("Driver V2 · 2.0.0 (200024) · production");
    expect(mapProviderLabel(info)).toBe("Google");
  });
});
