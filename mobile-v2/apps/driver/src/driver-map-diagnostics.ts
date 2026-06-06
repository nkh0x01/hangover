import type { DriverMapDiagnostics } from "@ride360/diagnostics";

import type {
  ActiveRideOffer,
  DashboardMapRegion,
  DriverRide,
  LocationPoint,
} from "./driver-dashboard";

export type DriverMapRuntimeConfig = {
  bundleId?: string;
  keyPresent: boolean;
  mapsKeyLength: number;
  mapsKeySha256Prefix?: string;
  provider: "apple" | "google";
};

export function buildMapDiagnostics({
  activeOffer,
  activeRide,
  currentLocation,
  errorMessage,
  googleProviderEnabled,
  lastRegion,
  loaded,
  mapConfig,
  ready,
}: {
  activeOffer?: ActiveRideOffer | null;
  activeRide?: DriverRide | null;
  currentLocation?: LocationPoint | null;
  errorMessage?: string;
  googleProviderEnabled: boolean;
  lastRegion?: DashboardMapRegion | null;
  loaded: boolean;
  mapConfig: DriverMapRuntimeConfig;
  ready: boolean;
}): DriverMapDiagnostics {
  const trip = activeRide ?? activeOffer;

  return {
    bundleId: mapConfig.bundleId,
    driverCoordinates: coordinateText(currentLocation),
    dropoffCoordinates: coordinateText(trip?.dropoff),
    errorMessage,
    googleProviderEnabled,
    keyPresent: mapConfig.keyPresent,
    mapsKeyLength: mapConfig.mapsKeyLength,
    mapsKeySha256Prefix: mapConfig.mapsKeySha256Prefix,
    lastRegion: regionText(lastRegion),
    loaded,
    pickupCoordinates: coordinateText(trip?.pickup),
    provider: mapConfig.provider,
    ready,
  };
}

function coordinateText(point?: {
  lat?: number | null;
  lng?: number | null;
} | null): string | undefined {
  if (typeof point?.lat !== "number" || typeof point.lng !== "number") {
    return undefined;
  }
  return `${point.lat.toFixed(6)},${point.lng.toFixed(6)}`;
}

function regionText(region?: DashboardMapRegion | null): string | undefined {
  if (!region) return undefined;
  return [
    region.latitude,
    region.longitude,
    region.latitudeDelta,
    region.longitudeDelta,
  ]
    .map((value) => value.toFixed(6))
    .join(",");
}
