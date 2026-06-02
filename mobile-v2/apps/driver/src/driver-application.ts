import { ApiError, type ApiClient } from "@ride360/api";
import type { DriverContext } from "@ride360/types";

export type DriverApplicationStatus =
  | "draft"
  | "needs_completion"
  | "needs_changes"
  | "rejected"
  | "submitted"
  | "pending"
  | "manual_review"
  | "approved"
  | string;

export type DriverApplicationDocument = {
  id: number;
  doc_type: string;
  status: string;
  preview_name: string;
  review_notes?: string | null;
};

export type DriverApplication = {
  id: number;
  status: DriverApplicationStatus;
  first_name: string | null;
  last_name: string | null;
  personal_id: string | null;
  phone_e164: string | null;
  email: string | null;
  birth_date: string | null;
  service_zone: string | null;
  driver_type: string | null;
  vehicle_type: string | null;
  vehicle_brand: string | null;
  vehicle_model: string | null;
  vehicle_year: number | null;
  vehicle_color: string | null;
  vehicle_plate: string | null;
  engine_cc: string | null;
  insurance_expires_on: string | null;
  inspection_expires_on: string | null;
  information_confirmed: boolean;
  terms_accepted: boolean;
  privacy_accepted: boolean;
  rejection_reason?: string | null;
  admin_note?: string | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;
  decision_reason?: string | null;
  manual_review_reasons?: string[];
  documents?: DriverApplicationDocument[];
  missing_required_fields?: string[];
  missing_documents?: string[];
  can_auto_approve?: boolean;
};

export type DriverApplicationForm = {
  first_name: string;
  last_name: string;
  personal_id: string;
  phone_e164: string;
  email: string;
  birth_date: string;
  service_zone: string;
  driver_type: string;
  vehicle_type: string;
  vehicle_brand: string;
  vehicle_model: string;
  vehicle_year: string;
  vehicle_color: string;
  vehicle_plate: string;
  engine_cc: string;
  insurance_expires_on: string;
  inspection_expires_on: string;
  information_confirmed: boolean;
  terms_accepted: boolean;
  privacy_accepted: boolean;
};

export type DriverApplicationEnvelope = {
  data: DriverApplication | null;
  driver_context: DriverContext;
};

export type ApplicationFieldErrors = Partial<
  Record<keyof DriverApplicationForm | "documents", string>
>;

export type ApplicationClient = {
  getApplication: () => Promise<DriverApplicationEnvelope>;
  saveDraft: (
    form: DriverApplicationForm,
    existingApplicationId?: number | null,
  ) => Promise<DriverApplicationEnvelope>;
  submit: () => Promise<DriverApplicationEnvelope>;
};

export const APPLICATION_STEPS = [
  "პირადი ინფორმაცია",
  "ტრანსპორტი",
  "დოკუმენტები",
  "დადასტურება",
] as const;

export const blankApplicationForm: DriverApplicationForm = {
  first_name: "",
  last_name: "",
  personal_id: "",
  phone_e164: "",
  email: "",
  birth_date: "",
  service_zone: "",
  driver_type: "",
  vehicle_type: "",
  vehicle_brand: "",
  vehicle_model: "",
  vehicle_year: "",
  vehicle_color: "",
  vehicle_plate: "",
  engine_cc: "",
  insurance_expires_on: "",
  inspection_expires_on: "",
  information_confirmed: false,
  terms_accepted: false,
  privacy_accepted: false,
};

export function createDriverApplicationClient(api: ApiClient): ApplicationClient {
  return {
    async getApplication() {
      try {
        const response =
          await api.get<DriverApplicationEnvelope>("/driver/application");
        return {
          data: response.data,
          driver_context: response.driver_context,
        };
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
          return {
            data: null,
            driver_context: blankDriverContext(),
          };
        }
        throw error;
      }
    },
    async saveDraft(form, existingApplicationId) {
      const payload = formToPayload(form);
      return existingApplicationId
        ? api.put<DriverApplicationEnvelope>("/driver/application", payload)
        : api.post<DriverApplicationEnvelope>("/driver/application", payload);
    },
    async submit() {
      return api.post<DriverApplicationEnvelope>("/driver/application/submit");
    },
  };
}

export function formFromApplication(
  application: DriverApplication | null,
  fallbackPhone?: string | null,
): DriverApplicationForm {
  if (!application) {
    return {
      ...blankApplicationForm,
      phone_e164: fallbackPhone ?? "",
    };
  }

  return {
    first_name: application.first_name ?? "",
    last_name: application.last_name ?? "",
    personal_id: application.personal_id ?? "",
    phone_e164: application.phone_e164 ?? fallbackPhone ?? "",
    email: application.email ?? "",
    birth_date: application.birth_date ?? "",
    service_zone: application.service_zone ?? "",
    driver_type: application.driver_type ?? "",
    vehicle_type: application.vehicle_type ?? "",
    vehicle_brand: application.vehicle_brand ?? "",
    vehicle_model: application.vehicle_model ?? "",
    vehicle_year: application.vehicle_year?.toString() ?? "",
    vehicle_color: application.vehicle_color ?? "",
    vehicle_plate: application.vehicle_plate ?? "",
    engine_cc: application.engine_cc ?? "",
    insurance_expires_on: application.insurance_expires_on ?? "",
    inspection_expires_on: application.inspection_expires_on ?? "",
    information_confirmed: Boolean(application.information_confirmed),
    terms_accepted: Boolean(application.terms_accepted),
    privacy_accepted: Boolean(application.privacy_accepted),
  };
}

export function formToPayload(form: DriverApplicationForm) {
  return {
    first_name: nullable(form.first_name),
    last_name: nullable(form.last_name),
    personal_id: nullable(form.personal_id),
    phone_e164: nullable(form.phone_e164),
    email: nullable(form.email),
    birth_date: nullable(form.birth_date),
    service_zone: nullable(form.service_zone),
    driver_type: nullable(form.driver_type),
    vehicle_type: nullable(form.vehicle_type),
    vehicle_brand: nullable(form.vehicle_brand),
    vehicle_model: nullable(form.vehicle_model),
    vehicle_year: form.vehicle_year.trim() ? Number(form.vehicle_year) : null,
    vehicle_color: nullable(form.vehicle_color),
    vehicle_plate: nullable(form.vehicle_plate),
    engine_cc: nullable(form.engine_cc),
    insurance_expires_on: nullable(form.insurance_expires_on),
    inspection_expires_on: nullable(form.inspection_expires_on),
    information_confirmed: form.information_confirmed,
    terms_accepted: form.terms_accepted,
    privacy_accepted: form.privacy_accepted,
  };
}

export function validateApplicationForm(
  form: DriverApplicationForm,
): ApplicationFieldErrors {
  const errors: ApplicationFieldErrors = {};

  if (!form.first_name.trim()) errors.first_name = "სახელი აუცილებელია.";
  if (!form.last_name.trim()) errors.last_name = "გვარი აუცილებელია.";
  if (!/^\d{9,11}$/.test(form.personal_id.trim())) {
    errors.personal_id = "პირადი ნომერი უნდა იყოს 9-11 ციფრი.";
  }
  if (!/^\+9955\d{8}$/.test(form.phone_e164.trim())) {
    errors.phone_e164 = "ტელეფონი უნდა იყოს ფორმატით +9955XXXXXXXX.";
  }
  if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = "ელფოსტა არასწორია.";
  }
  if (!form.birth_date.trim()) errors.birth_date = "დაბადების თარიღი აუცილებელია.";
  if (!form.service_zone.trim()) errors.service_zone = "ზონა აუცილებელია.";
  if (!form.driver_type.trim()) errors.driver_type = "აირჩიეთ მძღოლის ტიპი.";
  if (!form.vehicle_type.trim()) errors.vehicle_type = "აირჩიეთ ტრანსპორტის ტიპი.";
  if (!form.vehicle_brand.trim()) errors.vehicle_brand = "ბრენდი აუცილებელია.";
  if (!form.vehicle_model.trim()) errors.vehicle_model = "მოდელი აუცილებელია.";
  if (!/^\d{4}$/.test(form.vehicle_year.trim())) {
    errors.vehicle_year = "წელი უნდა იყოს 4 ციფრი.";
  }
  if (!form.vehicle_color.trim()) errors.vehicle_color = "ფერი აუცილებელია.";
  if (!form.vehicle_plate.trim()) errors.vehicle_plate = "ნომერი აუცილებელია.";
  if (!form.information_confirmed) {
    errors.information_confirmed = "დაადასტურეთ ინფორმაციის სისწორე.";
  }
  if (!form.terms_accepted) errors.terms_accepted = "დაეთანხმეთ პირობებს.";
  if (!form.privacy_accepted) {
    errors.privacy_accepted = "დაეთანხმეთ კონფიდენციალურობას.";
  }

  return errors;
}

export function fieldErrorsFromApiError(error: unknown): ApplicationFieldErrors {
  if (!(error instanceof ApiError)) return {};
  const details = error.details as
    | { fields?: Record<string, string[] | string> }
    | Record<string, string[] | string>
    | undefined;
  const fields =
    details && "fields" in details && details.fields ? details.fields : details;
  if (!fields) return {};

  return Object.fromEntries(
    Object.entries(fields).map(([key, value]) => [
      key,
      Array.isArray(value) ? value.join(" ") : String(value),
    ]),
  ) as ApplicationFieldErrors;
}

export function isApplicationReadOnly(
  application: DriverApplication | null,
  context?: DriverContext,
): boolean {
  const status = application?.status ?? context?.application_status;
  return status === "pending" || status === "submitted" || status === "manual_review";
}

export function shouldRouteApprovedToDashboard(
  application: DriverApplication | null,
  context?: DriverContext,
): boolean {
  return application?.status === "approved" || context?.can_go_online === true;
}

export function statusTitle(
  application: DriverApplication | null,
  context?: DriverContext,
): string {
  const status = application?.status ?? context?.application_status;
  if (status === "approved") return "განაცხადი დადასტურებულია";
  if (status === "pending" || status === "submitted") return "განაცხადი გაგზავნილია";
  if (status === "manual_review") return "განაცხადი შემოწმებაზეა";
  if (status === "needs_changes") return "საჭიროა ცვლილებები";
  if (status === "rejected") return "განაცხადი უარყოფილია";
  return "მძღოლის განაცხადი";
}

function nullable(value: string): string | null {
  const trimmed = value.trim();
  return trimmed ? trimmed : null;
}

function blankDriverContext(): DriverContext {
  return {
    has_driver_profile: false,
    application_status: null,
    needs_application: true,
    can_submit_application: true,
    can_go_online: false,
    reason_if_cannot_go_online: "driver.no_profile",
  };
}
