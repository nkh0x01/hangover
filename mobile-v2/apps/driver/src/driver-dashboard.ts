import { ApiError, ApiNetworkError, ApiTimeoutError, type ApiClient } from "@ride360/api";
import type { ApiEnvelope, DriverContext, User } from "@ride360/types";

export type ShiftStatus = "online" | "offline";

export type LocationPoint = {
  lat: number;
  lng: number;
};

export type DriverMeResponse = ApiEnvelope<User>;

export type OnlineResponse = ApiEnvelope<{
  online: boolean;
  online_since?: string | null;
}>;

export type ActiveRideOffer = {
  ride_ulid: string;
  expires_at: string;
  pickup?: {
    address?: string | null;
  } | null;
  dropoff?: {
    address?: string | null;
  } | null;
  distance_to_pickup_m?: number | null;
  fare?: {
    amount?: number | null;
    currency?: string | null;
  } | null;
};

export type DriverRide = {
  id: string;
  status: string;
  pickup?: {
    address?: string | null;
    lat?: number | null;
    lng?: number | null;
  } | null;
  dropoff?: {
    address?: string | null;
    lat?: number | null;
    lng?: number | null;
  } | null;
  fare?: {
    quoted?: number | null;
    final?: number | null;
    currency?: string | null;
  } | null;
  timestamps?: Record<string, string | null> | null;
};

export type DashboardError = {
  title: string;
  message: string;
  requestId?: string;
  kind: "permission" | "validation" | "server" | "network" | "unknown";
};

export type DashboardClient = {
  getDriverMe: () => Promise<User>;
  goOnline: (location: LocationPoint) => Promise<OnlineResponse["data"]>;
  goOffline: () => Promise<OnlineResponse["data"]>;
  getActiveOffer: () => Promise<ActiveRideOffer | null>;
  getActiveRide: () => Promise<DriverRide | null>;
  acceptOffer: (rideUlid: string) => Promise<DriverRide>;
  rejectOffer: (rideUlid: string) => Promise<{ rejected: boolean }>;
  markArriving: (rideUlid: string) => Promise<DriverRide>;
  markArrived: (rideUlid: string) => Promise<DriverRide>;
  startRide: (rideUlid: string) => Promise<DriverRide>;
  completeRide: (rideUlid: string) => Promise<DriverRide>;
};

export function createDriverDashboardClient(api: ApiClient): DashboardClient {
  return {
    async getDriverMe() {
      const response = await api.get<DriverMeResponse>("/driver/me");
      return response.data;
    },
    async goOnline(location) {
      const response = await api.post<OnlineResponse>("/driver/status/online", {
        lat: location.lat,
        lng: location.lng,
      });
      return response.data;
    },
    async goOffline() {
      const response = await api.post<OnlineResponse>("/driver/status/offline");
      return response.data;
    },
    async getActiveOffer() {
      const response = await api.get<ApiEnvelope<ActiveRideOffer | null>>(
        "/driver/offers/active",
      );
      return response.data;
    },
    async getActiveRide() {
      const response = await api.get<ApiEnvelope<DriverRide | null>>(
        "/driver/rides/active",
      );
      return response.data;
    },
    async acceptOffer(rideUlid) {
      const response = await api.post<ApiEnvelope<DriverRide>>(
        `/driver/offers/${encodeURIComponent(rideUlid)}/accept`,
      );
      return response.data;
    },
    async rejectOffer(rideUlid) {
      const response = await api.post<ApiEnvelope<{ rejected: boolean }>>(
        `/driver/offers/${encodeURIComponent(rideUlid)}/reject`,
      );
      return response.data;
    },
    async markArriving(rideUlid) {
      const response = await api.post<ApiEnvelope<DriverRide>>(
        `/driver/rides/${encodeURIComponent(rideUlid)}/arriving`,
      );
      return response.data;
    },
    async markArrived(rideUlid) {
      const response = await api.post<ApiEnvelope<DriverRide>>(
        `/driver/rides/${encodeURIComponent(rideUlid)}/arrived`,
      );
      return response.data;
    },
    async startRide(rideUlid) {
      const response = await api.post<ApiEnvelope<DriverRide>>(
        `/driver/rides/${encodeURIComponent(rideUlid)}/start`,
      );
      return response.data;
    },
    async completeRide(rideUlid) {
      const response = await api.post<ApiEnvelope<DriverRide>>(
        `/driver/rides/${encodeURIComponent(rideUlid)}/complete`,
      );
      return response.data;
    },
  };
}

export function canOpenDashboard(user?: User): boolean {
  const context = user?.driver_context;
  if (!user || user.type !== "driver" || !context) return false;
  if (!context.has_driver_profile) return false;
  if (context.driver_profile_status !== "approved") return false;
  if (!["active", "verified"].includes(context.vehicle_status ?? "")) return false;
  return context.can_go_online || Boolean(context.reason_if_cannot_go_online);
}

export function dashboardBlockReason(user?: User): string | null {
  const context = user?.driver_context;
  if (!user || user.type !== "driver") return "მძღოლის ანგარიში საჭიროა.";
  if (!context) return "მძღოლის კონტექსტი ვერ მოიძებნა.";
  if (!context.has_driver_profile) return "მძღოლის პროფილი ჯერ არ არის დადასტურებული.";
  if (context.driver_profile_status !== "approved") {
    return `მძღოლის სტატუსი: ${context.driver_profile_status ?? "უცნობია"}.`;
  }
  if (!["active", "verified"].includes(context.vehicle_status ?? "")) {
    return `ტრანსპორტის სტატუსი: ${context.vehicle_status ?? "არ არის"}.`;
  }
  if (!context.can_go_online) {
    return context.reason_if_cannot_go_online ?? "ონლაინ გასვლა დროებით შეუძლებელია.";
  }
  return null;
}

export function shiftStatusFromContext(context?: DriverContext): ShiftStatus {
  return context?.online_status ? "online" : "offline";
}

export function rideStatusText(status?: string | null): string {
  switch (status) {
    case "accepted":
      return "მიღებულია";
    case "driver_arriving":
      return "მძღოლი გზაშია";
    case "driver_arrived":
      return "მძღოლი მივიდა";
    case "in_progress":
      return "მგზავრობა მიმდინარეობს";
    case "completed":
      return "დასრულებულია";
    case "cancelled":
      return "გაუქმებულია";
    default:
      return status ?? "უცნობია";
  }
}

export function nextRideActionLabel(status?: string | null): string | null {
  switch (status) {
    case "accepted":
      return "გზაში ვარ";
    case "driver_arriving":
      return "მივედი";
    case "driver_arrived":
      return "მგზავრობის დაწყება";
    case "in_progress":
      return "მგზავრობის დასრულება";
    default:
      return null;
  }
}

export function dashboardErrorFrom(error: unknown): DashboardError {
  if (error instanceof ApiError) {
    if (error.status === 403) {
      return {
        title: "ონლაინ გასვლა შეუძლებელია",
        message: backendMessage(error) ?? "ამ მოქმედებისთვის მძღოლის უფლება ან პირობა არ არის დაკმაყოფილებული.",
        requestId: error.requestId,
        kind: "permission",
      };
    }
    if (error.status === 422) {
      return {
        title: "ლოკაცია არასწორია",
        message: backendMessage(error) ?? "გადაამოწმეთ ლოკაცია და სცადეთ თავიდან.",
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

  if (error instanceof ApiTimeoutError || error instanceof ApiNetworkError) {
    return {
      title: "ქსელის შეცდომა",
      message: "კავშირი ვერ დამყარდა. გადაამოწმეთ ინტერნეტი და სცადეთ თავიდან.",
      kind: "network",
    };
  }

  if (error instanceof Error) {
    return {
      title: "შეცდომა",
      message: error.message,
      kind: "unknown",
    };
  }

  return {
    title: "შეცდომა",
    message: "უცნობი შეცდომა.",
    kind: "unknown",
  };
}

function backendMessage(error: ApiError): string | null {
  const body = error.body as
    | { error?: { message?: string; code?: string }; message?: string }
    | null;
  return body?.error?.message ?? body?.error?.code ?? body?.message ?? null;
}
