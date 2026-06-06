import { describe, expect, it } from "vitest";

import { buildMapDiagnostics } from "./driver-map-diagnostics";

describe("driver map diagnostics", () => {
  it("reports Google provider and key-present production config", () => {
    expect(
      buildMapDiagnostics({
        currentLocation: { lat: 41.7151, lng: 44.8271 },
        googleProviderEnabled: true,
        loaded: true,
        mapConfig: {
          bundleId: "app.ride360.driver",
          keyPresent: true,
          mapsKeyLength: 39,
          mapsKeySha256Prefix: "9242c62ce14f",
          provider: "google",
        },
        ready: true,
      }),
    ).toMatchObject({
      bundleId: "app.ride360.driver",
      driverCoordinates: "41.715100,44.827100",
      googleProviderEnabled: true,
      keyPresent: true,
      mapsKeyLength: 39,
      mapsKeySha256Prefix: "9242c62ce14f",
      provider: "google",
      ready: true,
    });
  });

  it("reports pickup and dropoff coordinates for active offers", () => {
    expect(
      buildMapDiagnostics({
        activeOffer: {
          ride_ulid: "01RIDE",
          expires_at: "2026-06-06T20:00:00Z",
          pickup: { lat: 41.7151, lng: 44.8271 },
          dropoff: { lat: 41.725, lng: 44.79 },
        },
        googleProviderEnabled: true,
        loaded: false,
        mapConfig: {
          keyPresent: true,
          mapsKeyLength: 39,
          mapsKeySha256Prefix: "9242c62ce14f",
          provider: "google",
        },
        ready: true,
      }),
    ).toMatchObject({
      dropoffCoordinates: "41.725000,44.790000",
      pickupCoordinates: "41.715100,44.827100",
    });
  });

  it("blank or missing coordinates do not crash diagnostics", () => {
    expect(
      buildMapDiagnostics({
        activeOffer: {
          ride_ulid: "01RIDE",
          expires_at: "2026-06-06T20:00:00Z",
          pickup: { address: "Pickup only" },
          dropoff: null,
        },
        currentLocation: null,
        googleProviderEnabled: false,
        loaded: false,
        mapConfig: {
          keyPresent: false,
          mapsKeyLength: 0,
          provider: "google",
        },
        ready: false,
      }),
    ).toMatchObject({
      driverCoordinates: undefined,
      dropoffCoordinates: undefined,
      googleProviderEnabled: false,
      keyPresent: false,
      pickupCoordinates: undefined,
    });
  });
});
