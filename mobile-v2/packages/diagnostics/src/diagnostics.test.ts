import { describe, expect, it } from "vitest";

import { createDiagnosticsStore } from "./index";

describe("diagnostics store", () => {
  it("records driver dashboard build, online, offer, and map state", () => {
    const diagnostics = createDiagnosticsStore();

    diagnostics.recordDriverDashboard({
      activeOfferId: null,
      appBuildNumber: "200025",
      appEnv: "production",
      appVersion: "2.0.0",
      lastOfferPollStatus: "none",
      lastStatusEndpointResponse: "online:true",
      map: {
        bundleId: "app.ride360.driver",
        googleProviderEnabled: true,
        keyPresent: true,
        loaded: false,
        mapsKeyLength: 39,
        mapsKeySha256Prefix: "9242c62ce14f",
        provider: "google",
        ready: true,
      },
      mapProvider: "Google",
      online: true,
    });

    expect(diagnostics.getState().driverDashboard).toEqual({
      activeOfferId: null,
      appBuildNumber: "200025",
      appEnv: "production",
      appVersion: "2.0.0",
      lastOfferPollStatus: "none",
      lastStatusEndpointResponse: "online:true",
      map: {
        bundleId: "app.ride360.driver",
        googleProviderEnabled: true,
        keyPresent: true,
        loaded: false,
        mapsKeyLength: 39,
        mapsKeySha256Prefix: "9242c62ce14f",
        provider: "google",
        ready: true,
      },
      mapProvider: "Google",
      online: true,
    });
  });
});
