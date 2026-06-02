import { describe, expect, it } from "vitest";

import { ApiError, type ApiClient } from "@ride360/api";

import {
  createDriverApplicationClient,
  fieldErrorsFromApiError,
  formFromApplication,
  isApplicationReadOnly,
  shouldRouteApprovedToDashboard,
  statusTitle,
  validateApplicationForm,
  type DriverApplication,
  type DriverApplicationEnvelope,
} from "./driver-application";

const context = {
  has_driver_profile: false,
  application_status: null,
  needs_application: true,
  can_submit_application: true,
  can_go_online: false,
  reason_if_cannot_go_online: "driver.no_profile",
};

const existing: DriverApplication = {
  id: 7,
  status: "draft",
  first_name: "ნიკა",
  last_name: "მძღოლი",
  personal_id: "123456789",
  phone_e164: "+995555123456",
  email: "driver@example.com",
  birth_date: "1990-01-01",
  service_zone: "თბილისი",
  driver_type: "moto",
  vehicle_type: "scooter_petrol",
  vehicle_brand: "Honda",
  vehicle_model: "PCX",
  vehicle_year: 2020,
  vehicle_color: "შავი",
  vehicle_plate: "AA123BB",
  engine_cc: "125",
  insurance_expires_on: "2027-01-01",
  inspection_expires_on: "2027-02-01",
  information_confirmed: true,
  terms_accepted: true,
  privacy_accepted: true,
};

function mockApi() {
  const calls: Array<{ method: string; path: string; body?: unknown }> = [];
  const envelope: DriverApplicationEnvelope = { data: existing, driver_context: context };
  const api: ApiClient = {
    request: async () => envelope as never,
    get: async (path) => {
      calls.push({ method: "GET", path });
      return envelope as never;
    },
    post: async (path, body) => {
      calls.push({ method: "POST", path, body });
      return envelope as never;
    },
    put: async (path, body) => {
      calls.push({ method: "PUT", path, body });
      return envelope as never;
    },
  };
  return { api, calls };
}

describe("driver application client", () => {
  it("missing application opens blank form", () => {
    const form = formFromApplication(null, "+995555000111");
    expect(form.first_name).toBe("");
    expect(form.phone_e164).toBe("+995555000111");
  });

  it("existing application pre-fills", () => {
    const form = formFromApplication(existing);
    expect(form.first_name).toBe("ნიკა");
    expect(form.vehicle_year).toBe("2020");
  });

  it("save draft POST works", async () => {
    const { api, calls } = mockApi();
    await createDriverApplicationClient(api).saveDraft(formFromApplication(existing), null);
    expect(calls[0]).toMatchObject({ method: "POST", path: "/driver/application" });
  });

  it("update draft PUT works", async () => {
    const { api, calls } = mockApi();
    await createDriverApplicationClient(api).saveDraft(formFromApplication(existing), 7);
    expect(calls[0]).toMatchObject({ method: "PUT", path: "/driver/application" });
  });

  it("submit works", async () => {
    const { api, calls } = mockApi();
    await createDriverApplicationClient(api).submit();
    expect(calls[0]).toMatchObject({
      method: "POST",
      path: "/driver/application/submit",
    });
  });
});

describe("driver application states", () => {
  it("validation errors shown", () => {
    const errors = validateApplicationForm({ ...formFromApplication(null), first_name: "" });
    expect(errors.first_name).toBe("სახელი აუცილებელია.");
  });

  it("pending status screen is read-only", () => {
    expect(isApplicationReadOnly({ ...existing, status: "pending" })).toBe(true);
    expect(statusTitle({ ...existing, status: "pending" })).toBe("განაცხადი გაგზავნილია");
  });

  it("needs_changes screen is editable", () => {
    expect(isApplicationReadOnly({ ...existing, status: "needs_changes" })).toBe(false);
    expect(statusTitle({ ...existing, status: "needs_changes" })).toBe("საჭიროა ცვლილებები");
  });

  it("approved routes dashboard", () => {
    expect(shouldRouteApprovedToDashboard({ ...existing, status: "approved" })).toBe(true);
  });

  it("parses backend validation fields", () => {
    const error = new ApiError("Validation error", 422, "validation.failed", {}, {
      fields: { first_name: ["Required"] },
    });
    expect(fieldErrorsFromApiError(error)).toEqual({ first_name: "Required" });
  });
});
