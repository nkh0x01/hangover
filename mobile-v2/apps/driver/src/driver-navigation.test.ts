import { describe, expect, it } from "vitest";

import {
  COORDINATES_MISSING_MESSAGE,
  OPEN_MAP_LABEL,
  buildNavigationTargets,
  destinationForTrip,
  openNavigation,
  shouldShowNavigationButton,
} from "./driver-navigation";

describe("driver navigation", () => {
  it("exposes the full-screen map action label", () => {
    expect(OPEN_MAP_LABEL).toBe("რუკის გახსნა");
  });

  it("builds navigation URLs in pilot fallback order", () => {
    expect(
      buildNavigationTargets({
        lat: 41.7151,
        lng: 44.8271,
        label: "აყვანა",
      }).map((target) => [target.provider, target.url]),
    ).toEqual([
      [
        "google",
        "comgooglemaps://?daddr=41.7151,44.8271&directionsmode=driving",
      ],
      [
        "yandex",
        "yandexnavi://build_route_on_map?lat_to=41.7151&lon_to=44.8271",
      ],
      ["waze", "waze://?ll=41.7151,44.8271&navigate=yes"],
      [
        "apple",
        "http://maps.apple.com/?daddr=41.7151,44.8271&dirflg=d&q=%E1%83%90%E1%83%A7%E1%83%95%E1%83%90%E1%83%9C%E1%83%90",
      ],
      [
        "browser",
        "https://www.google.com/maps/dir/?api=1&destination=41.7151,44.8271&travelmode=driving",
      ],
    ]);
  });

  it("falls through unavailable navigation schemes", async () => {
    const opened: string[] = [];
    const result = await openNavigation({
      canOpenURL: async (url) => url.startsWith("waze://"),
      destination: { lat: 41.7151, lng: 44.8271, label: "აყვანა" },
      openURL: async (url) => {
        opened.push(url);
      },
    });

    expect(result.provider).toBe("waze");
    expect(opened).toEqual(["waze://?ll=41.7151,44.8271&navigate=yes"]);
  });

  it("uses pickup coordinates for offers and dropoff after pickup arrival", () => {
    expect(
      destinationForTrip({
        activeOffer: {
          ride_ulid: "01RIDE",
          expires_at: "2026-06-07T10:00:00Z",
          pickup: { lat: 41.7151, lng: 44.8271 },
          dropoff: { lat: 41.725, lng: 44.79 },
        },
      }),
    ).toMatchObject({ label: "აყვანა", lat: 41.7151, lng: 44.8271 });

    expect(
      destinationForTrip({
        activeRide: {
          id: "01RIDE",
          status: "in_progress",
          pickup: { lat: 41.7151, lng: 44.8271 },
          dropoff: { lat: 41.725, lng: 44.79 },
        },
      }),
    ).toMatchObject({ label: "დანიშნულება", lat: 41.725, lng: 44.79 });
  });

  it("hides navigation when coordinates are missing", () => {
    const destination = destinationForTrip({
      activeOffer: {
        ride_ulid: "01RIDE",
        expires_at: "2026-06-07T10:00:00Z",
        pickup: { address: "No coords" },
      },
    });

    expect(destination).toBeNull();
    expect(shouldShowNavigationButton(destination)).toBe(false);
    expect(COORDINATES_MISSING_MESSAGE).toBe("კოორდინატები ვერ მოიძებნა");
  });
});
