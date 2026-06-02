import type { AppRole } from "@ride360/config";

export type ApiEnvelope<T> = {
  data: T;
};

export type ApiErrorPayload = {
  error?: {
    code?: string;
    message?: string;
    details?: unknown;
    request_id?: string;
  };
  message?: string;
};

export type User = {
  id: string;
  type: AppRole | string | null;
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
  phone_verified: boolean;
  locale: string | null;
  status: string | null;
  driver_context?: DriverContext;
};

export type AuthVerifyResponse = {
  token: string;
  expires_at: string;
  abilities: string[];
  is_new?: boolean;
  user: User;
};

export type DriverContext = {
  has_driver_profile: boolean;
  driver_profile_id?: number | null;
  driver_profile_status?: string | null;
  application_status: string | null;
  application_id?: number | null;
  needs_application: boolean;
  can_submit_application: boolean;
  vehicle_status?: string | null;
  vehicle_id?: number | null;
  today_earnings?: string | null;
  online_status?: boolean | null;
  can_go_online: boolean;
  reason_if_cannot_go_online: string | null;
  rejection_reason?: string | null;
  missing_required_fields?: string[];
  missing_fields?: string[];
  missing_documents?: string[];
};
