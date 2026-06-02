import { describe, expect, it } from "vitest";

import { createDiagnosticsStore } from "@ride360/diagnostics";

import { ApiError, createApiClient } from "./index";

function response(body: unknown, status: number): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

describe("api client", () => {
  it("parses API errors and records redacted diagnostics", async () => {
    const diagnostics = createDiagnosticsStore();
    const api = createApiClient({
      config: { API_BASE_URL: "https://ride.365sakartvelo.com" },
      diagnostics,
      getToken: () => "secret-token",
      fetchImpl: async () =>
        response(
          {
            error: {
              code: "validation.failed",
              message: "Validation error",
              details: { token: "secret-token", phone: ["Invalid"] },
              request_id: "req_123",
            },
          },
          422,
        ),
    });

    await expect(api.post("/auth/otp/request", { phone: "bad" })).rejects.toBeInstanceOf(
      ApiError,
    );

    const state = diagnostics.getState();
    expect(state.lastRequestMethod).toBe("POST");
    expect(state.lastRequestUrl).toBe(
      "https://ride.365sakartvelo.com/api/v1/auth/otp/request",
    );
    expect(state.lastStatus).toBe(422);
    expect(state.tokenPresent).toBe(true);
    expect(state.lastBodyExcerpt).toContain("validation.failed");
    expect(state.lastBodyExcerpt).not.toContain("secret-token");
  });

  it("maps 401 to session expired when backend code is absent", async () => {
    const api = createApiClient({
      config: { API_BASE_URL: "https://ride.365sakartvelo.com" },
      fetchImpl: async () => response({ message: "Unauthenticated" }, 401),
    });

    await expect(api.get("/auth/me")).rejects.toMatchObject({
      status: 401,
      code: "auth.session_expired",
    });
  });
});
