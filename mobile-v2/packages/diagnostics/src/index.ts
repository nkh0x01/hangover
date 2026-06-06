export type DiagnosticsRequest = {
  method: string;
  url: string;
  headers?: Record<string, unknown>;
  tokenPresent?: boolean;
};

export type DiagnosticsResponse = {
  method: string;
  url: string;
  status: number;
  bodyExcerpt?: string;
};

export type DiagnosticsError = {
  method?: string;
  url?: string;
  status?: number;
  code?: string;
  message: string;
  details?: unknown;
  bodyExcerpt?: string;
};

export type DiagnosticsState = {
  lastRequest?: DiagnosticsRequest;
  lastRequestMethod?: string;
  lastRequestUrl?: string;
  lastStatus?: number;
  lastBodyExcerpt?: string;
  lastNetworkException?: string;
  tokenPresent?: boolean;
  tokenAbilities: string[];
  authUserType?: string | null;
  currentRoute?: string;
  driverDashboard?: DriverDashboardDiagnostics;
  lastResponse?: DiagnosticsResponse;
  lastError?: DiagnosticsError;
  events: Array<{
    type: "request" | "response" | "error";
    at: string;
    payload: unknown;
  }>;
};

export type DriverDashboardDiagnostics = {
  activeOfferId?: string | null;
  appBuildNumber?: string;
  appEnv?: string;
  appVersion?: string;
  lastOfferPollStatus?: string;
  lastStatusEndpointResponse?: string;
  map?: DriverMapDiagnostics;
  mapProvider?: string;
  online?: boolean;
};

export type DriverMapDiagnostics = {
  bundleId?: string;
  driverCoordinates?: string;
  dropoffCoordinates?: string;
  errorMessage?: string;
  googleProviderEnabled?: boolean;
  keyPresent?: boolean;
  mapsKeyLength?: number;
  mapsKeySha256Prefix?: string;
  lastRegion?: string;
  loaded?: boolean;
  pickupCoordinates?: string;
  provider?: string;
  ready?: boolean;
};

export type DiagnosticsStore = {
  getState: () => DiagnosticsState;
  subscribe: (listener: (state: DiagnosticsState) => void) => () => void;
  recordRequest: (request: DiagnosticsRequest) => void;
  recordResponse: (response: DiagnosticsResponse) => void;
  recordError: (error: DiagnosticsError) => void;
  recordAuth: (auth: { abilities: string[]; userType?: string | null }) => void;
  recordCurrentRoute: (route: string) => void;
  recordDriverDashboard: (dashboard: DriverDashboardDiagnostics) => void;
  clear: () => void;
};

const REDACTED = "[redacted]";
const SENSITIVE_KEYS = [
  "authorization",
  "token",
  "access_token",
  "refresh_token",
  "api_key",
  "otp",
];

export function redactTokens<T>(value: T): T {
  if (Array.isArray(value)) {
    return value.map((item) => redactTokens(item)) as T;
  }

  if (value !== null && typeof value === "object") {
    return Object.fromEntries(
      Object.entries(value).map(([key, child]) => {
        const normalized = key.toLowerCase();
        if (normalized === "tokenpresent" || normalized === "token_present") {
          return [key, redactTokens(child)];
        }
        if (
          normalized === "code" &&
          typeof child === "string" &&
          /^\d{4,8}$/.test(child)
        ) {
          return [key, REDACTED];
        }
        const isSensitive = SENSITIVE_KEYS.some((needle) =>
          normalized.includes(needle),
        );
        return [key, isSensitive ? REDACTED : redactTokens(child)];
      }),
    ) as T;
  }

  return value;
}

export function bodyExcerpt(value: unknown, maxLength = 700): string {
  const redacted = redactTokens(value);
  let text: string;

  try {
    text = typeof redacted === "string" ? redacted : JSON.stringify(redacted);
  } catch {
    text = String(redacted);
  }

  return text.length <= maxLength ? text : `${text.slice(0, maxLength)}...`;
}

export function createDiagnosticsStore(): DiagnosticsStore {
  let state: DiagnosticsState = { events: [], tokenAbilities: [] };
  const listeners = new Set<(state: DiagnosticsState) => void>();

  function emit(next: DiagnosticsState) {
    state = next;
    for (const listener of listeners) listener(state);
  }

  function appendEvent(
    type: "request" | "response" | "error",
    payload: unknown,
  ) {
    return [
      ...state.events,
      { type, at: new Date().toISOString(), payload: redactTokens(payload) },
    ].slice(-50);
  }

  return {
    getState: () => state,
    subscribe(listener) {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    recordRequest(request) {
      const safeRequest = redactTokens(request);
      emit({
        ...state,
        lastRequest: safeRequest,
        lastRequestMethod: safeRequest.method,
        lastRequestUrl: safeRequest.url,
        tokenPresent: safeRequest.tokenPresent,
        lastError: undefined,
        lastNetworkException: undefined,
        events: appendEvent("request", safeRequest),
      });
    },
    recordResponse(response) {
      const safeResponse = redactTokens(response);
      emit({
        ...state,
        lastResponse: safeResponse,
        lastStatus: safeResponse.status,
        lastBodyExcerpt: safeResponse.bodyExcerpt,
        events: appendEvent("response", safeResponse),
      });
    },
    recordError(error) {
      const safeError = redactTokens(error);
      emit({
        ...state,
        lastError: safeError,
        lastStatus: safeError.status,
        lastBodyExcerpt: safeError.bodyExcerpt,
        lastNetworkException: safeError.message,
        events: appendEvent("error", safeError),
      });
    },
    recordAuth(auth) {
      emit({
        ...state,
        tokenAbilities: [...auth.abilities],
        authUserType: auth.userType,
      });
    },
    recordCurrentRoute(route) {
      emit({
        ...state,
        currentRoute: route,
      });
    },
    recordDriverDashboard(dashboard) {
      emit({
        ...state,
        driverDashboard: redactTokens(dashboard),
      });
    },
    clear() {
      emit({ events: [], tokenAbilities: [] });
    },
  };
}

export const diagnosticsStore = createDiagnosticsStore();
