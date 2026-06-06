import type { ActiveRideOffer, DriverRide } from "./driver-dashboard";

export type NavigationProvider =
  | "google"
  | "yandex"
  | "waze"
  | "apple"
  | "browser";

export type NavigationDestination = {
  lat: number;
  lng: number;
  label: string;
};

export type NavigationTarget = NavigationDestination & {
  provider: NavigationProvider;
  title: string;
  url: string;
};

export type OpenNavigationResult = {
  provider: NavigationProvider;
};

export const OPEN_MAP_LABEL = "რუკის გახსნა";
export const OPEN_NAVIGATION_LABEL = "ნავიგაციის გახსნა";
export const NAVIGATION_UNAVAILABLE_MESSAGE = "ნავიგაციის აპი ვერ გაიხსნა";
export const COORDINATES_MISSING_MESSAGE = "კოორდინატები ვერ მოიძებნა";

export function buildNavigationTargets(
  destination: NavigationDestination,
): NavigationTarget[] {
  const lat = formatCoordinate(destination.lat);
  const lng = formatCoordinate(destination.lng);
  const encodedLabel = encodeURIComponent(destination.label);

  return [
    {
      ...destination,
      provider: "google",
      title: "Google Maps-ით გახსნა",
      url: `comgooglemaps://?daddr=${lat},${lng}&directionsmode=driving`,
    },
    {
      ...destination,
      provider: "yandex",
      title: "Yandex Navigator-ით გახსნა",
      url: `yandexnavi://build_route_on_map?lat_to=${lat}&lon_to=${lng}`,
    },
    {
      ...destination,
      provider: "waze",
      title: "Waze-ით გახსნა",
      url: `waze://?ll=${lat},${lng}&navigate=yes`,
    },
    {
      ...destination,
      provider: "apple",
      title: "Apple Maps-ით გახსნა",
      url: `http://maps.apple.com/?daddr=${lat},${lng}&dirflg=d&q=${encodedLabel}`,
    },
    {
      ...destination,
      provider: "browser",
      title: "ბრაუზერში გახსნა",
      url: `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`,
    },
  ];
}

export async function openNavigation({
  canOpenURL,
  destination,
  openURL,
}: {
  canOpenURL: (url: string) => Promise<boolean>;
  destination: NavigationDestination;
  openURL: (url: string) => Promise<unknown>;
}): Promise<OpenNavigationResult> {
  let lastError: unknown;

  for (const target of buildNavigationTargets(destination)) {
    try {
      const canOpen = await canOpenURL(target.url);
      if (!canOpen) continue;
      await openURL(target.url);
      return { provider: target.provider };
    } catch (error) {
      lastError = error;
    }
  }

  throw lastError instanceof Error
    ? lastError
    : new Error(NAVIGATION_UNAVAILABLE_MESSAGE);
}

export function destinationForTrip({
  activeOffer,
  activeRide,
}: {
  activeOffer?: ActiveRideOffer | null;
  activeRide?: DriverRide | null;
}): NavigationDestination | null {
  const trip = activeRide ?? activeOffer;
  if (!trip) return null;

  const pickup = coordinateFromTripPoint(trip.pickup);
  const dropoff = coordinateFromTripPoint(trip.dropoff);

  if (activeRide && shouldNavigateToDropoff(activeRide.status) && dropoff) {
    return { ...dropoff, label: "დანიშნულება" };
  }

  if (pickup) return { ...pickup, label: "აყვანა" };
  if (dropoff) return { ...dropoff, label: "დანიშნულება" };
  return null;
}

export function shouldShowNavigationButton(
  destination?: NavigationDestination | null,
): boolean {
  return Boolean(destination);
}

function shouldNavigateToDropoff(status?: string | null): boolean {
  return status === "driver_arrived" || status === "in_progress";
}

function coordinateFromTripPoint(point?: {
  lat?: number | null;
  lng?: number | null;
} | null): Pick<NavigationDestination, "lat" | "lng"> | null {
  const lat = point?.lat;
  const lng = point?.lng;
  if (!validCoordinate(lat, lng)) return null;
  return { lat: lat as number, lng: lng as number };
}

function validCoordinate(lat?: number | null, lng?: number | null): boolean {
  return (
    typeof lat === "number" &&
    typeof lng === "number" &&
    Number.isFinite(lat) &&
    Number.isFinite(lng) &&
    lat >= -90 &&
    lat <= 90 &&
    lng >= -180 &&
    lng <= 180
  );
}

function formatCoordinate(value: number): string {
  return value.toFixed(6).replace(/0+$/, "").replace(/\.$/, "");
}
