import { describe, expect, it } from "vitest";

import { createApiClient } from "@ride360/api";
import { createDiagnosticsStore, redactTokens } from "@ride360/diagnostics";

import {
  createAuthClient,
  createAuthStore,
  createDeviceUuid,
  createMemoryTokenStorage,
  isCustomerToken,
  isDriverOnboardingToken,
  isValidUuid,
  normalizeGeorgianPhone,
} from "./index";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function createMockApi(
  handler: (request: { url: string; init: RequestInit }) => Response,
) {
  const calls: Array<{ url: string; init: RequestInit; body: unknown }> = [];
  const diagnostics = createDiagnosticsStore();
  const api = createApiClient({
    config: { API_BASE_URL: "https://ride.365sakartvelo.com" },
    diagnostics,
    fetchImpl: async (url, init) => {
      calls.push({
        url: String(url),
        init: init ?? {},
        body: init?.body ? JSON.parse(String(init.body)) : undefined,
      });
      return handler({ url: String(url), init: init ?? {} });
    },
  });

  return { api, calls, diagnostics };
}

const user = {
  id: "01H",
  type: "customer",
  first_name: null,
  last_name: null,
  phone: "+995555123456",
  phone_verified: true,
  locale: "ka",
  status: "active",
};

describe("normalizeGeorgianPhone", () => {
  it("normalizes supported Georgian mobile formats", () => {
    expect(normalizeGeorgianPhone("555123456")).toBe("+995555123456");
    expect(normalizeGeorgianPhone("995555123456")).toBe("+995555123456");
    expect(normalizeGeorgianPhone("+995 555-12-34-56")).toBe("+995555123456");
  });
});

describe("auth client", () => {
  it("sends normalized OTP request payload", async () => {
    const { api, calls } = createMockApi(() =>
      jsonResponse({ data: { resend_after: 60 } }, 202),
    );
    const auth = createAuthClient({ api });

    await auth.requestOtp({ phone: "555-12-34-56", purpose: "login" });

    expect(calls[0].url).toBe(
      "https://ride.365sakartvelo.com/api/v1/auth/otp/request",
    );
    expect(calls[0].body).toEqual({
      phone: "+995555123456",
      purpose: "login",
    });
  });

  it("sends OTP verify payload with valid UUID, platform, and app version", async () => {
    const storage = createMemoryTokenStorage();
    const { api, calls } = createMockApi(() =>
      jsonResponse({
        data: {
          token: "secret-token",
          expires_at: "2026-07-01T00:00:00Z",
          abilities: ["customer"],
          user,
        },
      }),
    );
    const auth = createAuthClient({ api, storage });

    await auth.verifyOtp({
      phone: "+995555123456",
      code: "111111",
      purpose: "login",
      platform: "ios",
      app_version: "0.1.0",
    });

    expect(calls[0].body).toMatchObject({
      phone: "+995555123456",
      code: "111111",
      purpose: "login",
      platform: "ios",
      app_version: "0.1.0",
    });
    expect(isValidUuid((calls[0].body as { device_uuid: string }).device_uuid)).toBe(
      true,
    );
  });

  it("persists token response and recognizes abilities", async () => {
    const storage = createMemoryTokenStorage();
    const store = createAuthStore();
    const { api } = createMockApi(() =>
      jsonResponse({
        data: {
          token: "secret-token",
          expires_at: "2026-07-01T00:00:00Z",
          abilities: ["driver:onboarding"],
          user: { ...user, type: "driver" },
        },
      }),
    );
    const auth = createAuthClient({ api, storage, store });

    await auth.verifyOtp({
      phone: "995555123456",
      code: "111111",
      purpose: "driver_signup",
      device_uuid: createDeviceUuid(),
      platform: "android",
      app_version: "0.1.0",
    });

    const persisted = await storage.read();
    expect(persisted?.token).toBe("secret-token");
    expect(persisted?.userType).toBe("driver");
    expect(isCustomerToken(["customer"])).toBe(true);
    expect(isDriverOnboardingToken(persisted?.abilities ?? [])).toBe(true);
    expect(store.getState().status).toBe("signedIn");
  });
});

describe("diagnostics redaction", () => {
  it("redacts token-like fields", () => {
    expect(
      redactTokens({
        Authorization: "Bearer secret",
        token: "secret",
        nested: { code: "111111", safe: "ok" },
      }),
    ).toEqual({
      Authorization: "[redacted]",
      token: "[redacted]",
      nested: { code: "[redacted]", safe: "ok" },
    });
  });
});
