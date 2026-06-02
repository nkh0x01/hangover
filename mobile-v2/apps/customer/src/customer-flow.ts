import { ApiError, ApiNetworkError, ApiTimeoutError, type ApiClient } from "@ride360/api";
import type { ApiEnvelope, User } from "@ride360/types";

export type CustomerScreen =
  | "welcome"
  | "phone"
  | "otp"
  | "home"
  | "diagnostics"
  | "role-mismatch";

export const CUSTOMER_OTP_PURPOSE = "login" as const;

export type FareEstimate = {
  id: string;
  currency: string;
  total_amount: number;
  base_fare: number;
  surge_multiplier: number;
  distance_km: number;
  duration_min: number;
  expires_at: string;
};

export type Ride = {
  id?: string;
  ulid?: string;
  status?: string;
};

export type NearbyDriver = {
  id: string | number;
  lat?: number;
  lng?: number;
  vehicle_type?: string;
  distance_meters?: number;
};

export type CustomerHomeForm = {
  pickupAddress: string;
  pickupLat: string;
  pickupLng: string;
  dropoffAddress: string;
  dropoffLat: string;
  dropoffLng: string;
  vehicleType: string;
};

export type CustomerHomeError = {
  title: string;
  message: string;
  requestId?: string;
  kind: "validation" | "permission" | "server" | "network" | "unknown";
};

export type CustomerClient = {
  getAuthMe: () => Promise<User>;
  getCustomerMe: () => Promise<User>;
  getNearbyDrivers: (lat: number, lng: number) => Promise<NearbyDriver[]>;
  getActiveRide: () => Promise<Ride | null>;
  getRideHistory: () => Promise<Ride[]>;
  getRide: (ulid: string) => Promise<Ride>;
  estimateFare: (form: CustomerHomeForm) => Promise<FareEstimate>;
  requestRide: (form: CustomerHomeForm, fareEstimateId: string) => Promise<Ride>;
  cancelRide: (ulid: string) => Promise<Ride>;
};

export const blankCustomerHomeForm: CustomerHomeForm = {
  pickupAddress: "თბილისი, თავისუფლების მოედანი",
  pickupLat: "41.6938",
  pickupLng: "44.8015",
  dropoffAddress: "თბილისი, ვაკე",
  dropoffLat: "41.7100",
  dropoffLng: "44.7500",
  vehicleType: "scooter_electric",
};

export function createCustomerClient(api: ApiClient): CustomerClient {
  return {
    async getAuthMe() {
      const response = await api.get<ApiEnvelope<User>>("/auth/me");
      return response.data;
    },
    async getCustomerMe() {
      const response = await api.get<ApiEnvelope<User>>("/customer/me");
      return response.data;
    },
    async getNearbyDrivers(lat, lng) {
      const response = await api.get<ApiEnvelope<NearbyDriver[]>>(
        `/customer/drivers/nearby?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
      );
      return response.data;
    },
    async getActiveRide() {
      const response = await api.get<ApiEnvelope<Ride | null>>("/customer/rides/active");
      return response.data;
    },
    async getRideHistory() {
      const response = await api.get<ApiEnvelope<Ride[]>>("/customer/rides");
      return response.data;
    },
    async getRide(ulid) {
      const response = await api.get<ApiEnvelope<Ride>>(
        `/customer/rides/${encodeURIComponent(ulid)}`,
      );
      return response.data;
    },
    async estimateFare(form) {
      const response = await api.post<ApiEnvelope<FareEstimate>>(
        "/customer/rides/estimates",
        estimatePayload(form),
      );
      return response.data;
    },
    async requestRide(form, fareEstimateId) {
      const response = await api.post<ApiEnvelope<Ride>>("/customer/rides", {
        fare_estimate_id: fareEstimateId,
        pickup: {
          lat: Number(form.pickupLat),
          lng: Number(form.pickupLng),
          address: form.pickupAddress,
        },
        dropoff: {
          lat: Number(form.dropoffLat),
          lng: Number(form.dropoffLng),
          address: form.dropoffAddress,
        },
        payment_method: "cash",
        note: null,
      });
      return response.data;
    },
    async cancelRide(ulid) {
      const response = await api.request<ApiEnvelope<Ride>>(
        `/customer/rides/${encodeURIComponent(ulid)}/cancel`,
        { method: "PATCH" },
      );
      return response.data;
    },
  };
}

export function routeAfterCustomerSession(
  user: User,
  abilities: readonly string[],
): CustomerScreen {
  if (!isCustomerAbility(abilities)) {
    return "role-mismatch";
  }
  return "home";
}

export function routeAfterCustomerMe(user: User): CustomerScreen {
  return routeAfterCustomerSession(user, ["customer"]);
}

export function isCustomerAbility(abilities: readonly string[]): boolean {
  return abilities.includes("customer");
}

export function estimatePayload(form: CustomerHomeForm) {
  return {
    pickup: {
      lat: Number(form.pickupLat),
      lng: Number(form.pickupLng),
    },
    dropoff: {
      lat: Number(form.dropoffLat),
      lng: Number(form.dropoffLng),
    },
    vehicle_type: form.vehicleType || "scooter_electric",
  };
}

export function customerErrorFrom(error: unknown): CustomerHomeError {
  if (error instanceof ApiError) {
    if (error.status === 403) {
      return {
        title: "წვდომა შეზღუდულია",
        message: backendMessage(error) ?? "ამ მოქმედებისთვის მომხმარებლის ანგარიში საჭიროა.",
        requestId: error.requestId,
        kind: "permission",
      };
    }
    if (error.status === 422) {
      return {
        title: "მონაცემები არასწორია",
        message: backendMessage(error) ?? "გადაამოწმეთ მისამართები და კოორდინატები.",
        requestId: error.requestId,
        kind: "validation",
      };
    }
    if (error.status >= 500) {
      return {
        title: "სერვერის შეცდომა",
        message: backendMessage(error) ?? "სერვერზე დროებითი შეცდომაა.",
        requestId: error.requestId,
        kind: "server",
      };
    }
    return {
      title: "შეცდომა",
      message: backendMessage(error) ?? error.message,
      requestId: error.requestId,
      kind: "unknown",
    };
  }

  if (error instanceof ApiNetworkError || error instanceof ApiTimeoutError) {
    return {
      title: "ქსელის შეცდომა",
      message: "კავშირი ვერ დამყარდა. სცადეთ თავიდან.",
      kind: "network",
    };
  }

  if (error instanceof Error) {
    return { title: "შეცდომა", message: error.message, kind: "unknown" };
  }

  return { title: "შეცდომა", message: "უცნობი შეცდომა.", kind: "unknown" };
}

function backendMessage(error: ApiError): string | null {
  const body = error.body as
    | {
        error?: {
          message?: string;
          code?: string;
          details?: { fields?: Record<string, string[]> };
        };
        message?: string;
      }
    | null;
  const firstFieldMessages = Object.values(body?.error?.details?.fields ?? {})[0];
  const firstFieldMessage = Array.isArray(firstFieldMessages)
    ? firstFieldMessages[0]
    : null;
  if (firstFieldMessage) return firstFieldMessage;

  return body?.error?.message ?? body?.error?.code ?? body?.message ?? null;
}
