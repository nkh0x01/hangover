import React, { Component, useState } from "react";
import {
  AppRegistry,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";

const version = process.env.EXPO_PUBLIC_APP_VERSION ?? "2.0.0";
const buildNumber = process.env.IOS_BUILD_NUMBER ?? "200019";
const apiBaseUrl =
  process.env.EXPO_PUBLIC_API_BASE_URL ?? "https://ride.365sakartvelo.com";

function DiagnosticDriverApp() {
  const [results, setResults] = useState({});
  const [activeId, setActiveId] = useState(null);
  const [importedApp, setImportedApp] = useState(null);
  const [renderMode, setRenderMode] = useState(null);

  if (renderMode?.kind === "full-boundary") {
    return React.createElement(
      DiagnosticErrorBoundary,
      { onBack: () => setRenderMode(null) },
      React.createElement(DiagnosticRenderHost, {
        Component: renderMode.Component,
        onBack: () => setRenderMode(null),
      }),
    );
  }

  if (renderMode?.kind === "minimal-shell") {
    return React.createElement(
      DiagnosticErrorBoundary,
      { onBack: () => setRenderMode(null) },
      React.createElement(DiagnosticRenderHost, {
        Component: renderMode.Component,
        onBack: () => setRenderMode(null),
      }),
    );
  }

  if (renderMode?.kind === "full-normal") {
    return React.createElement(renderMode.Component);
  }

  const tests = createTests({
    importedApp,
    setImportedApp,
    setRenderMode,
  });

  async function runTest(test) {
    setActiveId(test.id);
    setResults((current) => ({
      ...current,
      [test.id]: {
        status: "running",
        detail: test.runningLabel ?? "started",
      },
    }));

    try {
      const result = await test.run();
      setResults((current) => ({
        ...current,
        [test.id]: {
          status: "ok",
          detail: result?.detail || "ok",
          render: result?.render,
        },
      }));
    } catch (error) {
      setResults((current) => ({
        ...current,
        [test.id]: {
          status: "failed",
          detail: errorText(error),
        },
      }));
    } finally {
      setActiveId(null);
    }
  }

  return React.createElement(
    ScrollView,
    {
      style: { flex: 1, backgroundColor: "#f7f8fb" },
      contentContainerStyle: { padding: 24, gap: 12 },
    },
    React.createElement(Text, { style: styles.title }, "Ride 360 Driver Diagnostic Boot"),
    React.createElement(Text, { style: styles.body }, `Build ${buildNumber}`),
    React.createElement(Text, { style: styles.caption }, `Version ${version}`),
    React.createElement(Text, { style: styles.caption }, "Boot OK"),
    React.createElement(Text, { style: styles.caption }, "Tap one staged test at a time."),
    ...tests.map((test) =>
      React.createElement(
        View,
        { key: test.id, style: styles.panel },
        React.createElement(
          Pressable,
          {
            accessibilityRole: "button",
            disabled: activeId !== null || Boolean(test.disabled),
            onPress: () => runTest(test),
            style: ({ pressed }) => [
              styles.button,
              activeId !== null || test.disabled ? styles.buttonDisabled : null,
              pressed ? styles.buttonPressed : null,
            ],
          },
          React.createElement(Text, { style: styles.buttonText }, test.label),
        ),
        test.note
          ? React.createElement(Text, { style: styles.caption }, test.note)
          : null,
        React.createElement(ResultText, { result: results[test.id] }),
        results[test.id]?.render
          ? React.createElement(View, { style: styles.renderSlot }, results[test.id].render())
          : null,
      ),
    ),
  );
}

function createTests({ importedApp, setImportedApp, setRenderMode }) {
  return [
    {
      id: "import-full-app",
      label: "Import full App module only",
      runningLabel: "dynamic import ./src/App",
      run: async () => {
        const module = await import("./src/App");
        setImportedApp(() => module.default);
        return {
          detail: `imported: ${exportedKeys(module)}`,
        };
      },
    },
    {
      id: "render-full-boundary",
      label: "Render full App inside ErrorBoundary",
      disabled: !importedApp,
      note: importedApp ? "full App module is imported" : "import full App first",
      run: async () => {
        const Component = importedApp ?? (await import("./src/App")).default;
        setImportedApp(() => Component);
        setRenderMode({ kind: "full-boundary", Component });
        return {
          detail: "rendering inside diagnostic boundary",
        };
      },
    },
    {
      id: "render-minimal-shell",
      label: "Render minimal Driver shell",
      run: async () => {
        const module = await import("./src/driver-minimal-shell");
        setRenderMode({ kind: "minimal-shell", Component: module.default });
        return {
          detail: `rendering shell: ${exportedKeys(module)}`,
        };
      },
    },
    {
      id: "session-restore",
      label: "Run session restore only",
      runningLabel: "reading secure token storage",
      run: async () => {
        const authModule = await import("@ride360/auth");
        const storage = authModule.createSecureTokenStorage();
        const session = await storage.read();
        if (!session) {
          return { detail: "no stored session" };
        }
        return {
          detail: `token=${session.token ? "present" : "missing"} abilities=${(session.abilities ?? []).join("|") || "-"}`,
        };
      },
    },
    {
      id: "auth-me",
      label: "Run auth/me only",
      runningLabel: "reading token then GET /auth/me",
      run: async () => {
        const [apiModule, authModule, diagnosticsModule] = await Promise.all([
          import("@ride360/api"),
          import("@ride360/auth"),
          import("@ride360/diagnostics"),
        ]);
        const storage = authModule.createSecureTokenStorage();
        const session = await storage.read();
        if (!session?.token) {
          return { detail: "no token; auth/me skipped" };
        }

        const client = apiModule.createApiClient({
          config: { API_BASE_URL: apiBaseUrl },
          diagnostics: diagnosticsModule.diagnosticsStore,
          getToken: () => session.token,
          defaultHeaders: {
            "X-App-Version": version,
            "X-Platform": "ios",
            "Accept-Language": "ka",
          },
        });
        const response = await client.get("/auth/me");
        return {
          detail: bodyExcerpt(response),
        };
      },
    },
    {
      id: "load-normal-full-app",
      label: "Load normal Driver App",
      note: "comparison path: imports and renders without diagnostic boundary",
      run: async () => {
        const Component = importedApp ?? (await import("./src/App")).default;
        setImportedApp(() => Component);
        setRenderMode({ kind: "full-normal", Component });
        return { detail: "rendering normal full app" };
      },
    },
  ];
}

class DiagnosticErrorBoundary extends Component {
  state = { error: null, componentStack: "" };

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    this.setState({ componentStack: info?.componentStack ?? "" });
  }

  render() {
    if (this.state.error) {
      return React.createElement(
        View,
        {
          style: {
            flex: 1,
            justifyContent: "center",
            padding: 24,
            backgroundColor: "#f7f8fb",
          },
        },
        React.createElement(Text, { style: styles.title }, "Diagnostic JS error"),
        React.createElement(Text, { style: styles.body }, errorText(this.state.error)),
        React.createElement(
          Text,
          { style: styles.caption },
          compactText(this.state.componentStack || "no component stack"),
        ),
        React.createElement(
          Pressable,
          {
            accessibilityRole: "button",
            onPress: this.props.onBack,
            style: [styles.button, { marginTop: 16 }],
          },
          React.createElement(Text, { style: styles.buttonText }, "Back to diagnostics"),
        ),
      );
    }

    return this.props.children;
  }
}

function DiagnosticRenderHost({ Component, onBack }) {
  return React.createElement(
    View,
    { style: { flex: 1, backgroundColor: "#f7f8fb" } },
    React.createElement(
      Pressable,
      {
        accessibilityRole: "button",
        onPress: onBack,
        style: [styles.button, { borderRadius: 0 }],
      },
      React.createElement(Text, { style: styles.buttonText }, "Back to diagnostics"),
    ),
    React.createElement(View, { style: { flex: 1 } }, React.createElement(Component)),
  );
}

function ResultText({ result }) {
  if (!result) {
    return React.createElement(Text, { style: styles.caption }, "not tested");
  }

  return React.createElement(
    Text,
    {
      style: [
        styles.caption,
        result.status === "ok" ? styles.ok : null,
        result.status === "failed" ? styles.failed : null,
      ],
    },
    `${result.status}: ${result.detail}`,
  );
}

function exportedKeys(module) {
  return Object.keys(module).sort().slice(0, 8).join(", ") || "loaded";
}

function errorText(error) {
  if (error instanceof Error) {
    return `${error.name}: ${error.message}`;
  }

  return String(error);
}

function bodyExcerpt(value) {
  let text;
  try {
    text = typeof value === "string" ? value : JSON.stringify(value);
  } catch {
    text = String(value);
  }

  return compactText(text);
}

function compactText(text) {
  return text.length <= 500 ? text : `${text.slice(0, 500)}...`;
}

const styles = {
  title: {
    color: "#151922",
    fontSize: 22,
    fontWeight: "700",
    marginBottom: 4,
  },
  body: {
    color: "#384152",
    fontSize: 16,
    marginBottom: 8,
  },
  caption: {
    color: "#697386",
    fontSize: 13,
    marginBottom: 4,
  },
  panel: {
    backgroundColor: "#ffffff",
    borderColor: "#e4e7ee",
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
    padding: 12,
  },
  button: {
    alignItems: "center",
    backgroundColor: "#1557d8",
    borderRadius: 8,
    minHeight: 44,
    justifyContent: "center",
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  buttonPressed: {
    backgroundColor: "#103c8f",
  },
  buttonDisabled: {
    backgroundColor: "#aab2c0",
  },
  buttonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "700",
  },
  ok: {
    color: "#126f3a",
  },
  failed: {
    color: "#b42318",
  },
  renderSlot: {
    marginTop: 4,
  },
};

AppRegistry.registerComponent("main", () => DiagnosticDriverApp);
