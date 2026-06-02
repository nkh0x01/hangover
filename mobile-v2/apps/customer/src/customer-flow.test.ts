import { describe, expect, it } from "vitest";

import { ApiError, type ApiClient } from "@ride360/api";
import type { User } from "@ride360/types";

import {
  CUSTOMER_OTP_PURPOSE,
  blankCustomerHomeForm,
  createCustomerClient,
  customerErrorFrom,
  estimatePayload,
  isCustomerAbility,
  routeAfterCustomerMe,
  routeAfterCustomerSession,
} from "./customer-flow";

const customer: User = {
  id: "01H",
  type: "customer",
  first_name: null,
  last_name: null,
  phone: "+995555123456",
  phone_verified: true,
  locale: "ka",
  status: "active",
};

function mockApi() {
  const calls: Array<{ method: string; path: string; body?: unknown }> = [];
  const api: ApiClient = {
    request: async () => ({}) as never,
    get: async (path) => {
      calls.push({ method: "GET", path });
      return { data: path.includes("rides") ? null : customer } as never;
    },
    post: async (path, body) => {
      calls.push({ method: "POST", path, body });
      return {
        data: path.includes("estimates")
          ? { id: "estimate_1", total_amount: 7.5, currency: "GEL" }
          : { id: "ride_1", status: "requested" },
      } as never;
    },
    put: async () => ({}) as never,
  };
  return { api, calls };
}

describe("customer flow", () => {
  it("uses customer OTP purpose", () => {
    expect(CUSTOMER_OTP_PURPOSE).toBe("login");
  });

  it("customer token recognized", () => {
    expect(isCustomerAbility(["customer"])).toBe(true);
  });

  it("auth/me works and routes customer home", async () => {
    const { api } = mockApi();
    const user = await createCustomerClient(api).getAuthMe();
    expect(routeAfterCustomerMe(user)).toBe("home");
  });

  it("role mismatch handled", () => {
    expect(routeAfterCustomerSession({ ...customer, type: "driver" }, ["customer"])).toBe("home");
    expect(routeAfterCustomerSession(customer, ["driver"])).toBe("role-mismatch");
  });

  it("creates estimate payload", () => {
    expect(estimatePayload(blankCustomerHomeForm)).toEqual({
      pickup: { lat: 41.6938, lng: 44.8015 },
      dropoff: { lat: 41.71, lng: 44.75 },
      vehicle_type: "scooter_electric",
    });
  });

  it("estimate request works", async () => {
    const { api, calls } = mockApi();
    await createCustomerClient(api).estimateFare(blankCustomerHomeForm);
    expect(calls[0]).toMatchObject({
      method: "POST",
      path: "/customer/rides/estimates",
    });
  });

  it("nearby drivers endpoint works", async () => {
    const { api, calls } = mockApi();
    await createCustomerClient(api).getNearbyDrivers(41.6938, 44.8015);
    expect(calls[0]).toMatchObject({
      method: "GET",
      path: "/customer/drivers/nearby?lat=41.6938&lng=44.8015",
    });
  });

  it("ride detail endpoint works", async () => {
    const { api, calls } = mockApi();
    await createCustomerClient(api).getRide("01HX");
    expect(calls[0]).toMatchObject({
      method: "GET",
      path: "/customer/rides/01HX",
    });
  });

  it("ride cancel endpoint works", async () => {
    const calls: Array<{ method: string; path: string }> = [];
    const api: ApiClient = {
      ...mockApi().api,
      request: async (path, options) => {
        calls.push({ method: options?.method ?? "GET", path });
        return { data: { ulid: "01HX", status: "cancelled" } } as never;
      },
    };
    await createCustomerClient(api).cancelRide("01HX");
    expect(calls[0]).toMatchObject({
      method: "PATCH",
      path: "/customer/rides/01HX/cancel",
    });
  });

  it("estimate request validation does not become generic server error", () => {
    const mapped = customerErrorFrom(
      new ApiError("Validation", 422, "validation.failed", {
        error: { code: "validation.failed" },
      }),
    );
    expect(mapped.kind).toBe("validation");
    expect(mapped.title).toBe("მონაცემები არასწორია");
  });

  it("shows backend field validation detail instead of generic message", () => {
    const mapped = customerErrorFrom(
      new ApiError("Validation", 422, "validation.failed", {
        error: {
          code: "validation.failed",
          message: "Validation failed.",
          details: { fields: { purpose: ["The selected purpose is invalid."] } },
        },
      }),
    );
    expect(mapped.message).toBe("The selected purpose is invalid.");
  });
});
