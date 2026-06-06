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

export function shouldUseGoogleMapProvider(
  mapConfig: DriverMapRuntimeConfig,
): boolean {
  return mapConfig.provider === "google" && mapConfig.keyPresent;
}

export function buildMapDiagnostics({
  activeOffer,
  activeRide,
  currentLocation,
  errorMessage,
  fullScreenRouteActive,
  googleProviderEnabled,
  lastNavigationOpenUrlProvider,
  lastRegion,
  loaded,
  mapConfig,
  navigationError,
  ready,
  selectedNavigationProvider,
}: {
  activeOffer?: ActiveRideOffer | null;
  activeRide?: DriverRide | null;
  currentLocation?: LocationPoint | null;
  errorMessage?: string;
  fullScreenRouteActive?: boolean;
  googleProviderEnabled: boolean;
  lastNavigationOpenUrlProvider?: string;
  lastRegion?: DashboardMapRegion | null;
  loaded: boolean;
  mapConfig: DriverMapRuntimeConfig;
  navigationError?: string;
  ready: boolean;
  selectedNavigationProvider?: string;
}): DriverMapDiagnostics {
  const trip = activeRide ?? activeOffer;

  return {
    bundleId: mapConfig.bundleId,
    driverCoordinates: coordinateText(currentLocation),
    dropoffCoordinates: coordinateText(trip?.dropoff),
    errorMessage,
    fullScreenRouteActive,
    googleProviderEnabled,
    keyPresent: mapConfig.keyPresent,
    lastNavigationOpenUrlProvider,
    mapsKeyLength: mapConfig.mapsKeyLength,
    mapsKeySha256Prefix: mapConfig.mapsKeySha256Prefix,
    navigationError,
    lastRegion: regionText(lastRegion),
    loaded,
    pickupCoordinates: coordinateText(trip?.pickup),
    provider: mapConfig.provider,
    ready,
    selectedNavigationProvider,
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
