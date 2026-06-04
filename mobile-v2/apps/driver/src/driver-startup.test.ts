import { describe, expect, it } from "vitest";

import { shouldRenderNativeStartupScreen } from "./driver-startup";

describe("driver startup rendering", () => {
  it("uses native-safe startup UI before auth restore finishes", () => {
    expect(
      shouldRenderNativeStartupScreen({ loading: true, screen: "welcome" }),
    ).toBe(true);
  });

  it("does not keep native startup UI after boot or on other routes", () => {
    expect(
      shouldRenderNativeStartupScreen({ loading: false, screen: "welcome" }),
    ).toBe(false);
    expect(
      shouldRenderNativeStartupScreen({ loading: true, screen: "phone" }),
    ).toBe(false);
  });
});
