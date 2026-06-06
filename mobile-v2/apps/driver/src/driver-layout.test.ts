import { describe, expect, it } from "vitest";

import { driverHeaderTopPadding } from "./driver-layout";

describe("driver layout", () => {
  it("adds iOS header breathing room for the status bar", () => {
    expect(driverHeaderTopPadding("ios")).toBeGreaterThan(0);
  });

  it("uses Android status bar height when available", () => {
    expect(driverHeaderTopPadding("android", 24)).toBe(24);
  });

  it("does not add web header padding", () => {
    expect(driverHeaderTopPadding("web")).toBe(0);
  });
});
