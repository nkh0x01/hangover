import type { RuntimeConfig } from "@ride360/config";
import {
  bodyExcerpt,
  diagnosticsStore,
  type DiagnosticsStore,
  redactTokens,
} from "@ride360/diagnostics";
import type { ApiErrorPayload } from "@ride360/types";

export type ApiClientOptions = {
  config: Pick<RuntimeConfig, "API_BASE_URL">;
  timeoutMs?: number;
  getToken?: () => Promise<string | null> | string | null;
  diagnostics?: DiagnosticsStore;
  defaultHeaders?: Record<string, string>;
  fetchImpl?: typeof fetch;
};

export type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  headers?: Record<string, string>;
  body?: unknown;
  timeoutMs?: number;
};

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string,
    public readonly body: unknown,
    public readonly details?: unknown,
    public readonly requestId?: string,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export class ApiTimeoutError extends Error {
  constructor(public readonly timeoutMs: number) {
    super("Connection error: request timed out");
    this.name = "ApiTimeoutError";
  }
}

export class ApiNetworkError extends Error {
  constructor(message = "Connection error") {
    super(message);
    this.name = "ApiNetworkError";
  }
}

export type ApiClient = {
  request: <T>(path: string, options?: RequestOptions) => Promise<T>;
  get: <T>(path: string, options?: Omit<RequestOptions, "method">) => Promise<T>;
  post: <T>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, "method" | "body">,
  ) => Promise<T>;
  put: <T>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, "method" | "body">,
  ) => Promise<T>;
};

export function createApiClient(options: ApiClientOptions): ApiClient {
  const timeoutMs = options.timeoutMs ?? 15000;
  const diagnostics = options.diagnostics ?? diagnosticsStore;
  const baseUrl = options.config.API_BASE_URL.replace(/\/$/, "");
  const fetcher = options.fetchImpl ?? fetch;

  async function request<T>(
    path: string,
    requestOptions: RequestOptions = {},
  ): Promise<T> {
    const method = requestOptions.method ?? "GET";
    const url = `${baseUrl}/api/v1/${path.replace(/^\//, "")}`;
    const effectiveTimeoutMs = requestOptions.timeoutMs ?? timeoutMs;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), effectiveTimeoutMs);
    const token = options.getToken ? await options.getToken() : null;
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...options.defaultHeaders,
      ...requestOptions.headers,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };

    diagnostics.recordRequest({
      method,
      url,
      headers: redactTokens(headers),
      tokenPresent: Boolean(token),
    });

    try {
      const response = await fetcher(url, {
        method,
        headers,
        body:
          requestOptions.body === undefined
            ? undefined
            : JSON.stringify(requestOptions.body),
        signal: controller.signal,
      });
      const parsed = await parseJson(response);
      const excerpt = bodyExcerpt(parsed);

      diagnostics.recordResponse({
        method,
        url,
        status: response.status,
        bodyExcerpt: excerpt,
      });

      if (!response.ok) {
        throw apiErrorFromResponse(response.status, parsed);
      }

      return parsed as T;
    } catch (error) {
      const normalized = normalizeUnknownError(error, effectiveTimeoutMs);
      diagnostics.recordError({
        method,
        url,
        status: normalized instanceof ApiError ? normalized.status : undefined,
        code: normalized instanceof ApiError ? normalized.code : normalized.name,
        message: normalized.message,
        details: normalized instanceof ApiError ? normalized.details : undefined,
        bodyExcerpt:
          normalized instanceof ApiError
            ? bodyExcerpt(normalized.body)
            : undefined,
      });
      throw normalized;
    } finally {
      clearTimeout(timeout);
    }
  }

  return {
    request,
    get: (path, requestOptions) =>
      request(path, { ...requestOptions, method: "GET" }),
    post: (path, body, requestOptions) =>
      request(path, { ...requestOptions, method: "POST", body }),
    put: (path, body, requestOptions) =>
      request(path, { ...requestOptions, method: "PUT", body }),
  };
}

async function parseJson(response: Response): Promise<unknown> {
  const text = await response.text();
  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

function apiErrorFromResponse(status: number, payload: unknown): ApiError {
  const data = payload as ApiErrorPayload | null;
  const error = data?.error;
  const fallbackCode = codeForHttpStatus(status);

  return new ApiError(
    error?.message ?? data?.message ?? messageForHttpStatus(status),
    status,
    error?.code ?? fallbackCode,
    payload,
    error?.details,
    error?.request_id,
  );
}

function normalizeUnknownError(error: unknown, timeoutMs: number): Error {
  if (
    error instanceof ApiError ||
    error instanceof ApiTimeoutError ||
    error instanceof ApiNetworkError
  ) {
    return error;
  }

  if (error instanceof DOMException && error.name === "AbortError") {
    return new ApiTimeoutError(timeoutMs);
  }

  if (error instanceof Error) {
    if (error.name === "AbortError") return new ApiTimeoutError(timeoutMs);
    return new ApiNetworkError(error.message);
  }

  return new ApiNetworkError();
}

function codeForHttpStatus(status: number): string {
  if (status === 401) return "auth.session_expired";
  if (status === 403) return "auth.insufficient_permission";
  if (status === 404) return "api.not_found";
  if (status === 422) return "validation.failed";
  if (status >= 500) return "server.error";
  return "api.error";
}

function messageForHttpStatus(status: number): string {
  if (status === 401) return "Session expired";
  if (status === 403) return "Insufficient permission";
  if (status === 404) return "Missing endpoint or resource";
  if (status === 422) return "Validation error";
  if (status >= 500) return "Server error";
  return `HTTP ${status}`;
}
