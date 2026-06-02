import { describe, expect, it } from "vitest";

import type { DriverContext, User } from "@ride360/types";

import {
  driverStatusText,
  initialDriverScreen,
  isOnboardingState,
  routeAfterDriverMe,
  screenForWelcomeAction,
  shouldClearSessionOnStartupError,
  stateForWelcomeAction,
  WELCOME_ACTIONS,
} from "./driver-flow";

const baseContext: DriverContext = {
  has_driver_profile: false,
  application_status: null,
  needs_application: true,
  can_submit_application: true,
  can_go_online: false,
  reason_if_cannot_go_online: "driver.no_profile",
};

function driver(context: DriverContext): User {
  return {
    id: "01H",
    type: "driver",
    first_name: null,
    last_name: null,
    phone: "+995555123456",
    phone_verified: true,
    locale: "ka",
    status: "active",
    driver_context: context,
  };
}

describe("driver auth flow", () => {
  it("fresh launch shows welcome", () => {
    expect(initialDriverScreen(false)).toBe("welcome");
  });

  it("welcome actions open login or registration phone flow and diagnostics", () => {
    expect(WELCOME_ACTIONS.map((action) => action.title)).toEqual([
      "შესვლა",
      "მძღოლად რეგისტრაცია",
      "დიაგნოსტიკა",
    ]);
    expect(screenForWelcomeAction("login")).toBe("phone");
    expect(screenForWelcomeAction("registration")).toBe("phone");
    expect(screenForWelcomeAction("diagnostics")).toBe("diagnostics");
  });

  it("login button opens login phone flow", () => {
    expect(stateForWelcomeAction("login", "registration")).toEqual({
      screen: "phone",
      mode: "login",
    });
  });

  it("registration button opens registration phone flow", () => {
    expect(stateForWelcomeAction("registration", "login")).toEqual({
      screen: "phone",
      mode: "registration",
    });
  });

  it("diagnostics is accessible before login", () => {
    expect(stateForWelcomeAction("diagnostics", "login")).toEqual({
      screen: "diagnostics",
      mode: "login",
    });
  });

  it("driver:onboarding plus needs_application routes registration to application flow", () => {
    expect(routeAfterDriverMe(driver(baseContext), "registration", "post-auth")).toBe(
      "application",
    );
  });

  it("existing onboarding token opens onboarding status screen, not application directly", () => {
    expect(routeAfterDriverMe(driver(baseContext), "login", "startup")).toBe(
      "onboarding-status",
    );
  });

  it("startup 401 clears token and goes welcome", () => {
    expect(shouldClearSessionOnStartupError({ status: 401 })).toBe(true);
  });

  it("does not use generic server error text for onboarding states", () => {
    expect(isOnboardingState(baseContext)).toBe(true);
    expect(driverStatusText(baseContext)).toBe("განაცხადი ჯერ არ არის შევსებული.");
    expect(driverStatusText(baseContext)).not.toContain(
      "სერვერთან კავშირი ვერ მოხერხდა",
    );
  });

  it("approved driver routes to dashboard", () => {
    expect(
      routeAfterDriverMe(
        driver({
          ...baseContext,
          has_driver_profile: true,
          driver_profile_status: "approved",
          needs_application: false,
          can_submit_application: false,
          vehicle_status: "active",
          can_go_online: true,
          reason_if_cannot_go_online: null,
        }),
        "login",
        "post-auth",
      ),
    ).toBe("dashboard");
  });

  it("approved driver with clear blocked reason can still open dashboard", () => {
    expect(
      routeAfterDriverMe(
        driver({
          ...baseContext,
          has_driver_profile: true,
          driver_profile_status: "approved",
          needs_application: false,
          can_submit_application: false,
          vehicle_status: "active",
          can_go_online: false,
          reason_if_cannot_go_online: "driver.incomplete_profile",
        }),
        "login",
        "post-auth",
      ),
    ).toBe("dashboard");
  });
});
