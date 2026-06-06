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
        googleProviderEnabled: false,
        keyPresent: false,
        loaded: false,
        mapsKeyLength: 0,
        provider: "apple",
        ready: true,
      },
      mapProvider: "Apple/default",
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
        googleProviderEnabled: false,
        keyPresent: false,
        loaded: false,
        mapsKeyLength: 0,
        provider: "apple",
        ready: true,
      },
      mapProvider: "Apple/default",
      online: true,
    });
  });
});
