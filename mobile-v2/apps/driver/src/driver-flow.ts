import type { DriverContext, User } from "@ride360/types";

export type AuthMode = "login" | "registration";

export type DriverScreen =
  | "welcome"
  | "phone"
  | "otp"
  | "diagnostics"
  | "startup-error"
  | "onboarding-status"
  | "application"
  | "dashboard"
  | "role-mismatch";

export const DRIVER_OTP_PURPOSE = "driver_signup" as const;
export const WELCOME_ACTIONS = [
  {
    id: "login",
    title: "შესვლა",
    subtitle: "თუ უკვე დარეგისტრირებული ხართ მძღოლად",
  },
  {
    id: "registration",
    title: "მძღოლად რეგისტრაცია",
    subtitle: "შეავსეთ განაცხადი და დაელოდეთ დადასტურებას",
  },
  {
    id: "diagnostics",
    title: "დიაგნოსტიკა",
    subtitle: "",
  },
] as const;

export function initialDriverScreen(hasStoredToken: boolean): DriverScreen {
  return hasStoredToken ? "startup-error" : "welcome";
}

export function screenForWelcomeAction(
  action: "login" | "registration" | "diagnostics",
): DriverScreen {
  return action === "diagnostics" ? "diagnostics" : "phone";
}

export function stateForWelcomeAction(
  action: AuthMode | "diagnostics",
  currentMode: AuthMode,
): { screen: DriverScreen; mode: AuthMode } {
  return {
    screen: screenForWelcomeAction(action),
    mode: action === "diagnostics" ? currentMode : action,
  };
}

export function routeAfterDriverMe(
  user: User,
  mode: AuthMode,
  source: "startup" | "post-auth",
): DriverScreen {
  const context = user.driver_context;

  if (user.type !== "driver" || !context) return "role-mismatch";
  if (canOpenDriverDashboard(context)) return "dashboard";

  if (source === "startup") return "onboarding-status";

  if (mode === "registration" && canContinueApplication(context)) {
    return "application";
  }

  return "onboarding-status";
}

function canOpenDriverDashboard(context: DriverContext): boolean {
  const approved = context.has_driver_profile && context.driver_profile_status === "approved";
  const vehicleReady =
    context.vehicle_status === "active" || context.vehicle_status === "verified";
  return approved && vehicleReady && (context.can_go_online || Boolean(context.reason_if_cannot_go_online));
}

export function shouldClearSessionOnStartupError(error: unknown): boolean {
  return httpStatusFrom(error) === 401;
}

export function isStartupRecoverableError(error: unknown): boolean {
  const status = httpStatusFrom(error);
  return status === undefined || status >= 500;
}

export function driverStatusText(context?: DriverContext): string {
  if (!context) {
    return "ამ ანგარიშისთვის მძღოლის პროფილი ვერ მოიძებნა.";
  }

  if (context.can_go_online) {
    return "თქვენი მძღოლის ანგარიში დადასტურებულია.";
  }

  if (context.needs_application || context.reason_if_cannot_go_online === "driver.no_profile") {
    return "განაცხადი ჯერ არ არის შევსებული.";
  }

  if (context.can_submit_application) {
    return "განაცხადი მზად არის გასაგრძელებლად.";
  }

  if (context.application_status === "pending" || context.application_status === "submitted") {
    return "განაცხადი გადაგზავნილია და ელოდება დადასტურებას.";
  }

  if (context.application_status === "manual_review") {
    return "განაცხადი დამატებით შემოწმებაზეა.";
  }

  if (context.application_status === "rejected") {
    return "განაცხადი უარყოფილია. შეგიძლიათ შეასწოროთ მონაცემები და ხელახლა გაგზავნოთ.";
  }

  if (context.application_status === "needs_changes") {
    return "განაცხადს სჭირდება ცვლილებები.";
  }

  if (context.application_status === "needs_completion") {
    return "განაცხადი ბოლომდე არ არის შევსებული.";
  }

  return "განაცხადი მუშავდება.";
}

export function onboardingPrimaryLabel(context?: DriverContext): string {
  if (
    !context ||
    context.needs_application ||
    context.reason_if_cannot_go_online === "driver.no_profile"
  ) {
    return "განაცხადის შევსება";
  }

  return "განაცხადის გაგრძელება";
}

export function canContinueApplication(context: DriverContext): boolean {
  return (
    context.needs_application ||
    context.can_submit_application ||
    context.application_status === "rejected" ||
    context.application_status === "needs_changes" ||
    context.application_status === "needs_completion" ||
    context.reason_if_cannot_go_online === "driver.no_profile"
  );
}

export function isOnboardingState(context?: DriverContext): boolean {
  if (!context) return false;
  return (
    context.needs_application ||
    context.can_submit_application ||
    context.application_status === "pending" ||
    context.application_status === "submitted" ||
    context.application_status === "manual_review" ||
    context.application_status === "rejected" ||
    context.application_status === "needs_changes" ||
    context.application_status === "needs_completion" ||
    context.reason_if_cannot_go_online === "driver.no_profile"
  );
}

function httpStatusFrom(error: unknown): number | undefined {
  if (typeof error === "object" && error !== null && "status" in error) {
    const status = (error as { status?: unknown }).status;
    return typeof status === "number" ? status : undefined;
  }
  return undefined;
}
