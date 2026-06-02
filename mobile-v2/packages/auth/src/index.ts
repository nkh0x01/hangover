import type { ApiClient } from "@ride360/api";
import type { DiagnosticsStore } from "@ride360/diagnostics";
import { diagnosticsStore } from "@ride360/diagnostics";
import type { ApiEnvelope, AuthVerifyResponse, User } from "@ride360/types";

export type AuthSession =
  | { status: "booting" }
  | { status: "signedOut" }
  | {
      status: "signedIn";
      token: string;
      abilities: string[];
      expiresAt: string;
      user: User;
    };

export type OtpPurpose = "signup" | "login" | "driver_signup" | "rebind";
export type Platform = "ios" | "android" | "web";

export type TokenPersistence = {
  token: string;
  expiresAt: string;
  abilities: string[];
  userType: string | null;
  user: User;
};

export type TokenStorage = {
  read: () => Promise<TokenPersistence | null>;
  write: (session: TokenPersistence) => Promise<void>;
  clear: () => Promise<void>;
};

export type AuthStore = {
  getState: () => AuthSession;
  subscribe: (listener: (state: AuthSession) => void) => () => void;
  setBooting: () => void;
  setSignedOut: () => void;
  setSignedIn: (session: TokenPersistence) => void;
};

export type AuthClient = {
  requestOtp: (input: { phone: string; purpose: OtpPurpose }) => Promise<void>;
  verifyOtp: (input: {
    phone: string;
    code: string;
    purpose: OtpPurpose;
    device_uuid?: string;
    platform: Platform;
    app_version: string;
  }) => Promise<AuthSession>;
  getCurrentUser: () => Promise<User>;
  logout: () => Promise<void>;
  refresh: () => Promise<AuthSession>;
  clearSession: () => Promise<void>;
};

const SESSION_KEY = "ride360.v2.auth.session";
const GEORGIAN_PHONE_PATTERN = /^\+9955\d{8}$/;
const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function normalizeGeorgianPhone(input: string): string {
  const digits = input.replace(/[^\d]/g, "");

  if (/^5\d{8}$/.test(digits)) return `+995${digits}`;
  if (/^9955\d{8}$/.test(digits)) return `+${digits}`;

  throw new Error("Invalid Georgian mobile number");
}

export function isValidUuid(value: string): boolean {
  return UUID_PATTERN.test(value);
}

export function createDeviceUuid(): string {
  const cryptoLike = globalThis.crypto as
    | { randomUUID?: () => string; getRandomValues?: (array: Uint8Array) => void }
    | undefined;

  if (cryptoLike?.randomUUID) return cryptoLike.randomUUID();

  const bytes = new Uint8Array(16);
  if (cryptoLike?.getRandomValues) {
    cryptoLike.getRandomValues(bytes);
  } else {
    for (let index = 0; index < bytes.length; index += 1) {
      bytes[index] = Math.floor(Math.random() * 256);
    }
  }

  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, "0"));

  return [
    hex.slice(0, 4).join(""),
    hex.slice(4, 6).join(""),
    hex.slice(6, 8).join(""),
    hex.slice(8, 10).join(""),
    hex.slice(10, 16).join(""),
  ].join("-");
}

export function createAuthStore(initial: AuthSession = { status: "booting" }) {
  let state = initial;
  const listeners = new Set<(state: AuthSession) => void>();

  function emit(next: AuthSession) {
    state = next;
    for (const listener of listeners) listener(state);
  }

  return {
    getState: () => state,
    subscribe(listener) {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    setBooting: () => emit({ status: "booting" }),
    setSignedOut: () => emit({ status: "signedOut" }),
    setSignedIn: (session) =>
      emit({
        status: "signedIn",
        token: session.token,
        abilities: session.abilities,
        expiresAt: session.expiresAt,
        user: session.user,
      }),
  } satisfies AuthStore;
}

export function createMemoryTokenStorage(
  initial: TokenPersistence | null = null,
): TokenStorage {
  let value = initial;

  return {
    read: async () => value,
    write: async (session) => {
      value = session;
    },
    clear: async () => {
      value = null;
    },
  };
}

export function createSecureTokenStorage(): TokenStorage {
  if (runtimePlatform() === "web") {
    return createWebTokenStorage();
  }

  return {
    async read() {
      const SecureStore = await import("expo-secure-store");
      const raw = await SecureStore.getItemAsync(SESSION_KEY);
      return raw ? (JSON.parse(raw) as TokenPersistence) : null;
    },
    async write(session) {
      const SecureStore = await import("expo-secure-store");
      await SecureStore.setItemAsync(SESSION_KEY, JSON.stringify(session));
    },
    async clear() {
      const SecureStore = await import("expo-secure-store");
      await SecureStore.deleteItemAsync(SESSION_KEY);
    },
  };
}

function createWebTokenStorage(): TokenStorage {
  const memory = createMemoryTokenStorage();

  function webStorage(): Storage | null {
    try {
      return globalThis.localStorage ?? null;
    } catch {
      return null;
    }
  }

  return {
    async read() {
      const storage = webStorage();
      if (!storage) return memory.read();
      const raw = storage.getItem(SESSION_KEY);
      return raw ? (JSON.parse(raw) as TokenPersistence) : null;
    },
    async write(session) {
      const storage = webStorage();
      if (!storage) {
        await memory.write(session);
        return;
      }
      storage.setItem(SESSION_KEY, JSON.stringify(session));
    },
    async clear() {
      const storage = webStorage();
      if (!storage) {
        await memory.clear();
        return;
      }
      storage.removeItem(SESSION_KEY);
    },
  };
}

export function createAuthClient(input: {
  api: ApiClient;
  storage?: TokenStorage;
  store?: AuthStore;
  diagnostics?: DiagnosticsStore;
}): AuthClient {
  const storage = input.storage ?? createSecureTokenStorage();
  const store = input.store ?? createAuthStore();
  const diagnostics = input.diagnostics ?? diagnosticsStore;

  async function persist(data: AuthVerifyResponse): Promise<AuthSession> {
    const session: TokenPersistence = {
      token: data.token,
      expiresAt: data.expires_at,
      abilities: data.abilities,
      userType: data.user.type,
      user: data.user,
    };

    await storage.write(session);
    store.setSignedIn(session);
    diagnostics.recordAuth({
      abilities: session.abilities,
      userType: session.userType,
    });

    return store.getState();
  }

  async function clearSession(): Promise<void> {
    await storage.clear();
    store.setSignedOut();
    diagnostics.recordAuth({ abilities: [], userType: null });
  }

  return {
    async requestOtp({ phone, purpose }) {
      await input.api.post<ApiEnvelope<unknown>>("/auth/otp/request", {
        phone: normalizeGeorgianPhone(phone),
        purpose,
      });
    },
    async verifyOtp({ phone, code, purpose, device_uuid, platform, app_version }) {
      const deviceUuid = device_uuid ?? createDeviceUuid();
      if (!isValidUuid(deviceUuid)) {
        throw new Error("device_uuid must be a valid UUID");
      }

      const response = await input.api.post<ApiEnvelope<AuthVerifyResponse>>(
        "/auth/otp/verify",
        {
          phone: normalizeGeorgianPhone(phone),
          code,
          purpose,
          device_uuid: deviceUuid,
          platform,
          app_version,
        },
      );

      return persist(response.data);
    },
    async getCurrentUser() {
      const response = await input.api.get<ApiEnvelope<User>>("/auth/me");
      const session = store.getState();
      diagnostics.recordAuth({
        abilities: session.status === "signedIn" ? session.abilities : [],
        userType: response.data.type,
      });
      return response.data;
    },
    async logout() {
      try {
        await input.api.post<ApiEnvelope<unknown>>("/auth/logout");
      } finally {
        await clearSession();
      }
    },
    async refresh() {
      const response =
        await input.api.post<ApiEnvelope<AuthVerifyResponse>>("/auth/refresh");
      return persist(response.data);
    },
    clearSession,
  };
}

export function hasAbility(
  abilities: readonly string[],
  ability: string,
): boolean {
  return abilities.includes(ability);
}

export function isCustomerToken(abilities: readonly string[]): boolean {
  return hasAbility(abilities, "customer");
}

export function isDriverToken(abilities: readonly string[]): boolean {
  return hasAbility(abilities, "driver");
}

export function isDriverOnboardingToken(abilities: readonly string[]): boolean {
  return hasAbility(abilities, "driver:onboarding");
}

export const phonePattern = GEORGIAN_PHONE_PATTERN;

function runtimePlatform(): Platform {
  const env = globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
  };
  const os = env.process?.env?.EXPO_OS;
  if (os === "ios" || os === "android" || os === "web") return os;
  if (typeof document !== "undefined") return "web";
  return "ios";
}
