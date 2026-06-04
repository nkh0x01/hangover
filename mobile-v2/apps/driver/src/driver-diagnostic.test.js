import { readFileSync } from "node:fs";

import { describe, expect, it } from "vitest";

const diagnosticSource = readFileSync(
  new URL("../diagnostic-index.js", import.meta.url),
  "utf8",
);
const minimalShellSource = readFileSync(
  new URL("./driver-minimal-shell.tsx", import.meta.url),
  "utf8",
);
const appSource = readFileSync(new URL("./App.tsx", import.meta.url), "utf8");

describe("Driver diagnostic boot", () => {
  it("keeps suspected modules out of top-level imports", () => {
    const topLevelImports = diagnosticSource.slice(
      0,
      diagnosticSource.indexOf("const version"),
    );

    expect(topLevelImports).toContain('from "react"');
    expect(topLevelImports).toContain('from "react-native"');
    expect(topLevelImports).not.toContain("@ride360/");
    expect(topLevelImports).not.toContain("expo-location");
    expect(topLevelImports).not.toContain("expo-secure-store");
    expect(topLevelImports).not.toContain("./src/App");
  });

  it("exposes staged full-app diagnostic buttons", () => {
    expect(diagnosticSource).toContain("Import full App module only");
    expect(diagnosticSource).toContain("Render full App inside ErrorBoundary");
    expect(diagnosticSource).toContain("Render minimal Driver shell");
    expect(diagnosticSource).toContain("Run session restore only");
    expect(diagnosticSource).toContain("Run auth/me only");
    expect(diagnosticSource).toContain("Load normal Driver App");
  });

  it("uses dynamic imports for staged crash probes", () => {
    expect(diagnosticSource).toContain('await import("./src/App")');
    expect(diagnosticSource).toContain('await import("./src/driver-minimal-shell")');
    expect(diagnosticSource).toContain('await import("@ride360/auth")');
    expect(diagnosticSource).toContain('import("@ride360/api")');
    expect(diagnosticSource).toContain('import("@ride360/diagnostics")');
  });

  it("keeps the minimal shell free of startup side effects", () => {
    expect(minimalShellSource).not.toContain("createSecureTokenStorage");
    expect(minimalShellSource).not.toContain("createApiClient");
    expect(minimalShellSource).not.toContain("expo-location");
    expect(minimalShellSource).not.toContain("./App");
  });

  it("does not perform storage, API, or location calls at App module import time", () => {
    const beforeAppComponent = appSource.slice(
      0,
      appSource.indexOf("export default function App"),
    );

    expect(beforeAppComponent).not.toContain("getItemAsync");
    expect(beforeAppComponent).not.toContain("fetch(");
    expect(beforeAppComponent).not.toContain("requestForegroundPermissions");
    expect(beforeAppComponent).not.toContain("watchPosition");
    expect(beforeAppComponent).not.toContain("getCurrentUser()");
  });
});
