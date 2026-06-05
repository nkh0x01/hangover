import { describe, expect, it } from "vitest";

import { ApiError, ApiNetworkError, type ApiClient } from "@ride360/api";
import type { DriverContext, User } from "@ride360/types";

import {
  buildDashboardMapState,
  canOpenDashboard,
  createDriverDashboardClient,
  dashboardBlockReason,
  dashboardErrorFrom,
  nextRideActionLabel,
  rideStatusText,
  shiftStatusFromContext,
} from "./driver-dashboard";

const approvedContext: DriverContext = {
  has_driver_profile: true,
  driver_profile_status: "approved",
  application_status: "approved",
  needs_application: false,
  can_submit_application: false,
  vehicle_status: "active",
  vehicle_id: 12,
  can_go_online: true,
  reason_if_cannot_go_online: null,
  today_earnings: "14.50",
  online_status: false,
};

const approvedUser: User = {
  id: "01H",
  type: "driver",
  first_name: "ნიკა",
  last_name: "მძღოლი",
  phone: "+995555123456",
  phone_verified: true,
  locale: "ka",
  status: "active",
  driver_context: approvedContext,
};

function mockApi() {
  const calls: Array<{ method: string; path: string; body?: unknown }> = [];
  const api: ApiClient = {
    request: async () => ({}) as never,
    get: async (path) => {
      calls.push({ method: "GET", path });
      return { data: approvedUser } as never;
    },
    post: async (path, body) => {
      calls.push({ method: "POST", path, body });
      if (path.includes("/offers/") && path.endsWith("/reject")) {
        return { data: { rejected: true } } as never;
      }
      if (path.includes("/offers/") && path.endsWith("/accept")) {
        return { data: { id: "01RIDE", status: "accepted" } } as never;
      }
      if (path.endsWith("/arriving")) {
        return { data: { id: "01RIDE", status: "driver_arriving" } } as never;
      }
      if (path.endsWith("/arrived")) {
        return { data: { id: "01RIDE", status: "driver_arrived" } } as never;
      }
      if (path.endsWith("/start")) {
        return { data: { id: "01RIDE", status: "in_progress" } } as never;
      }
      if (path.endsWith("/complete")) {
        return { data: { id: "01RIDE", status: "completed" } } as never;
      }
      return { data: { online: path.includes("online") } } as never;
    },
    put: async () => ({}) as never,
  };
  return { api, calls };
}

describe("driver dashboard", () => {
  it("approved driver can open dashboard", () => {
    expect(canOpenDashboard(approvedUser)).toBe(true);
    expect(dashboardBlockReason(approvedUser)).toBeNull();
  });

  it("can_go_online=false shows reason", () => {
    const user = {
      ...approvedUser,
      driver_context: {
        ...approvedContext,
        can_go_online: false,
        reason_if_cannot_go_online: "driver.missing_vehicle",
      },
    };
    expect(canOpenDashboard(user)).toBe(true);
    expect(dashboardBlockReason(user)).toBe("driver.missing_vehicle");
  });

  it("online success updates UI", async () => {
    const { api, calls } = mockApi();
    const result = await createDriverDashboardClient(api).goOnline({
      lat: 41.7151,
      lng: 44.8271,
    });
    expect(result.online).toBe(true);
    expect(calls[0]).toEqual({
      method: "POST",
      path: "/driver/status/online",
      body: { lat: 41.7151, lng: 44.8271 },
    });
  });

  it("offline success updates UI", async () => {
    const { api, calls } = mockApi();
    const result = await createDriverDashboardClient(api).goOffline();
    expect(result.online).toBe(false);
    expect(calls[0]).toMatchObject({
      method: "POST",
      path: "/driver/status/offline",
    });
  });

  it("fetches the active offer", async () => {
    const { calls } = mockApi();
    const api = {
      ...mockApi().api,
      get: async (path: string) => {
        calls.push({ method: "GET", path });
        return { data: { ride_ulid: "01RIDE", expires_at: "2026-06-02T17:00:00Z" } } as never;
      },
    };

    const offer = await createDriverDashboardClient(api).getActiveOffer();
    expect(offer?.ride_ulid).toBe("01RIDE");
    expect(calls[0]).toMatchObject({
      method: "GET",
      path: "/driver/offers/active",
    });
  });

  it("accepts and rejects active offers", async () => {
    const { api, calls } = mockApi();
    const client = createDriverDashboardClient(api);

    await expect(client.acceptOffer("01RIDE")).resolves.toMatchObject({
      status: "accepted",
    });
    await expect(client.rejectOffer("01RIDE")).resolves.toMatchObject({
      rejected: true,
    });

    expect(calls[0]).toMatchObject({
      method: "POST",
      path: "/driver/offers/01RIDE/accept",
    });
    expect(calls[1]).toMatchObject({
      method: "POST",
      path: "/driver/offers/01RIDE/reject",
    });
  });

  it("walks driver ride lifecycle endpoints", async () => {
    const { api, calls } = mockApi();
    const client = createDriverDashboardClient(api);

    await expect(client.markArriving("01RIDE")).resolves.toMatchObject({
      status: "driver_arriving",
    });
    await expect(client.markArrived("01RIDE")).resolves.toMatchObject({
      status: "driver_arrived",
    });
    await expect(client.startRide("01RIDE")).resolves.toMatchObject({
      status: "in_progress",
    });
    await expect(client.completeRide("01RIDE")).resolves.toMatchObject({
      status: "completed",
    });

    expect(calls.map((call) => call.path)).toEqual([
      "/driver/rides/01RIDE/arriving",
      "/driver/rides/01RIDE/arrived",
      "/driver/rides/01RIDE/start",
      "/driver/rides/01RIDE/complete",
    ]);
  });

  it("403 shows permission/business reason", () => {
    const mapped = dashboardErrorFrom(
      new ApiError("Forbidden", 403, "driver.not_approved", {
        error: { code: "driver.not_approved", message: "driver.not_approved" },
      }),
    );
    expect(mapped.kind).toBe("permission");
    expect(mapped.message).toBe("driver.not_approved");
  });

  it("422 shows validation error", () => {
    expect(
      dashboardErrorFrom(new ApiError("Validation", 422, "validation.failed", {})).kind,
    ).toBe("validation");
  });

  it("500 shows diagnostics/request_id", () => {
    const mapped = dashboardErrorFrom(
      new ApiError("Server", 500, "server.error", {}, undefined, "req_123"),
    );
    expect(mapped.kind).toBe("server");
    expect(mapped.requestId).toBe("req_123");
  });

  it("no network shows network-specific error", () => {
    expect(dashboardErrorFrom(new ApiNetworkError()).kind).toBe("network");
  });

  it("shift status reads from context", () => {
    expect(shiftStatusFromContext({ ...approvedContext, online_status: true })).toBe(
      "online",
    );
  });

  it("maps ride status to Georgian labels and next actions", () => {
    expect(rideStatusText("driver_arrived")).toBe("მძღოლი მივიდა");
    expect(nextRideActionLabel("accepted")).toBe("გზაში ვარ");
    expect(nextRideActionLabel("completed")).toBeNull();
  });

  it("builds a default Tbilisi map state before coordinates exist", () => {
    const map = buildDashboardMapState({});

    expect(map.markers).toEqual([]);
    expect(map.region).toMatchObject({
      latitude: 41.7151,
      longitude: 44.8271,
    });
  });

  it("builds driver, pickup, and dropoff markers for active rides", () => {
    const map = buildDashboardMapState({
      currentLocation: { lat: 41.7151, lng: 44.8271 },
      activeRide: {
        id: "01RIDE",
        status: "accepted",
        pickup: { address: "Pickup", lat: 41.72, lng: 44.82 },
        dropoff: { address: "Dropoff", lat: 41.735, lng: 44.79 },
      },
    });

    expect(map.markers.map((marker) => marker.kind)).toEqual([
      "driver",
      "pickup",
      "dropoff",
    ]);
    expect(map.region.latitudeDelta).toBeGreaterThanOrEqual(0.02);
    expect(map.region.longitudeDelta).toBeGreaterThanOrEqual(0.02);
  });

  it("ignores invalid map coordinates", () => {
    const map = buildDashboardMapState({
      currentLocation: { lat: 141, lng: 44.8271 },
      activeOffer: {
        ride_ulid: "01RIDE",
        expires_at: "2026-06-05T20:00:00Z",
        pickup: { lat: 41.72, lng: 44.82 },
        dropoff: { lat: 41.735, lng: 244.79 },
      },
    });

    expect(map.markers.map((marker) => marker.kind)).toEqual(["pickup"]);
  });
});
