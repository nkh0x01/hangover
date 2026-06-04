import { describe, expect, it } from "vitest";

import { textSelectableProp } from "./text-defaults";

describe("shared Text defaults", () => {
  it("does not make native text selectable by default", () => {
    expect(textSelectableProp()).toEqual({});
  });

  it("keeps selectable opt-in", () => {
    expect(textSelectableProp(true)).toEqual({ selectable: true });
    expect(textSelectableProp(false)).toEqual({ selectable: false });
  });
});
