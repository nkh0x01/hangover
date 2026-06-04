import React, { useState } from "react";
import {
  AppRegistry,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";

const version = process.env.EXPO_PUBLIC_APP_VERSION ?? "2.0.0";
const buildNumber = process.env.IOS_BUILD_NUMBER ?? "200018";

const tests = [
  {
    id: "ui",
    label: "Test shared UI package",
    run: async () => {
      const module = await import("@ride360/ui");
      return {
        detail: exportedKeys(module),
        render: () =>
          React.createElement(
            module.Card,
            null,
            React.createElement(module.Text, { variant: "caption" }, "shared UI render ok"),
          ),
      };
    },
  },
  {
    id: "auth",
    label: "Test auth package",
    run: async () => {
      const module = await import("@ride360/auth");
      return { detail: exportedKeys(module) };
    },
  },
  {
    id: "api",
    label: "Test api package",
    run: async () => {
      const module = await import("@ride360/api");
      return { detail: exportedKeys(module) };
    },
  },
  {
    id: "diagnostics",
    label: "Test diagnostics package",
    run: async () => {
      const module = await import("@ride360/diagnostics");
      return { detail: exportedKeys(module) };
    },
  },
  {
    id: "location",
    label: "Test expo-location import",
    run: async () => {
      const module = await import("expo-location");
      return { detail: exportedKeys(module) };
    },
  },
  {
    id: "secure-store",
    label: "Test expo-secure-store import",
    run: async () => {
      const module = await import("expo-secure-store");
      return { detail: exportedKeys(module) };
    },
  },
  {
    id: "full-driver",
    label: "Load full Driver App",
    run: async () => {
      const module = await import("./src/App");
      return {
        detail: exportedKeys(module),
        fullApp: module.default,
      };
    },
  },
];

function DiagnosticDriverApp() {
  const [results, setResults] = useState({});
  const [activeId, setActiveId] = useState(null);
  const [FullDriverApp, setFullDriverApp] = useState(null);

  if (FullDriverApp) {
    return React.createElement(FullDriverApp);
  }

  async function runTest(test) {
    setActiveId(test.id);
    setResults((current) => ({
      ...current,
      [test.id]: {
        status: "running",
        detail: "import started",
      },
    }));

    try {
      const result = await test.run();
      setResults((current) => ({
        ...current,
        [test.id]: {
          status: "ok",
          detail: result.detail || "loaded",
          render: result.render,
        },
      }));

      if (result.fullApp) {
        setFullDriverApp(() => result.fullApp);
      }
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
    React.createElement(Text, { style: styles.caption }, "Tap one test at a time."),
    ...tests.map((test) =>
      React.createElement(
        View,
        { key: test.id, style: styles.panel },
        React.createElement(
          Pressable,
          {
            accessibilityRole: "button",
            disabled: activeId !== null,
            onPress: () => runTest(test),
            style: ({ pressed }) => [
              styles.button,
              activeId !== null ? styles.buttonDisabled : null,
              pressed ? styles.buttonPressed : null,
            ],
          },
          React.createElement(Text, { style: styles.buttonText }, test.label),
        ),
        React.createElement(ResultText, { result: results[test.id] }),
        results[test.id]?.render
          ? React.createElement(View, { style: styles.renderSlot }, results[test.id].render())
          : null,
      ),
    ),
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
  },
  caption: {
    color: "#697386",
    fontSize: 13,
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
