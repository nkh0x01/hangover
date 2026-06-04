import React, { useEffect, useState } from "react";
import { AppRegistry } from "react-native";

const stages = [
  "view-only",
  "single-ascii-text",
  "multiple-ascii-text",
  "pressable-ascii",
  "simple-styles",
  "georgian-text",
  "button-list",
];

function PrimitiveUiDriverApp() {
  const [stageIndex, setStageIndex] = useState(0);

  useEffect(() => {
    if (stageIndex >= 3) return undefined;

    const timer = setTimeout(() => {
      setStageIndex((current) => Math.min(current + 1, stages.length - 1));
    }, 3500);

    return () => clearTimeout(timer);
  }, [stageIndex]);

  return renderStage(stages[stageIndex], () => {
    setStageIndex((current) => Math.min(current + 1, stages.length - 1));
  });
}

function renderStage(stage, next) {
  const { Pressable, ScrollView, Text, View } = require("react-native");

  if (stage === "view-only") {
    return React.createElement(View);
  }

  if (stage === "single-ascii-text") {
    return React.createElement(Text, null, "Boot OK");
  }

  if (stage === "multiple-ascii-text") {
    return React.createElement(
      View,
      null,
      React.createElement(Text, null, "Ride 360 Driver V2"),
      React.createElement(Text, null, "Build 200021"),
      React.createElement(Text, null, "Multiple ASCII text OK"),
    );
  }

  if (stage === "pressable-ascii") {
    return React.createElement(
      View,
      null,
      React.createElement(Text, null, "Stage 4 Pressable"),
      React.createElement(
        Pressable,
        { onPress: next },
        React.createElement(Text, null, "Tap for simple styles"),
      ),
    );
  }

  if (stage === "simple-styles") {
    return React.createElement(
      View,
      {
        style: {
          flex: 1,
          backgroundColor: "#f7f8fb",
          padding: 24,
          justifyContent: "center",
        },
      },
      React.createElement(
        Text,
        { style: { color: "#151922", fontSize: 22, marginBottom: 12 } },
        "Stage 5 Simple Styles",
      ),
      React.createElement(
        Pressable,
        {
          onPress: next,
          style: {
            backgroundColor: "#151922",
            padding: 14,
          },
        },
        React.createElement(
          Text,
          { style: { color: "#ffffff", fontSize: 16 } },
          "Tap for Georgian text",
        ),
      ),
    );
  }

  if (stage === "georgian-text") {
    return React.createElement(
      View,
      {
        style: {
          flex: 1,
          backgroundColor: "#ffffff",
          padding: 24,
          justifyContent: "center",
        },
      },
      React.createElement(
        Text,
        { style: { color: "#151922", fontSize: 22, marginBottom: 12 } },
        "ეტაპი 6",
      ),
      React.createElement(
        Text,
        { style: { color: "#526070", fontSize: 18, marginBottom: 20 } },
        "ქართული ტექსტი მუშაობს",
      ),
      React.createElement(
        Pressable,
        {
          onPress: next,
          style: {
            backgroundColor: "#151922",
            padding: 14,
          },
        },
        React.createElement(
          Text,
          { style: { color: "#ffffff", fontSize: 16 } },
          "Tap for button list",
        ),
      ),
    );
  }

  return React.createElement(
    ScrollView,
    {
      style: { flex: 1, backgroundColor: "#f7f8fb" },
      contentContainerStyle: { padding: 24, gap: 12 },
    },
    React.createElement(
      Text,
      { style: { color: "#151922", fontSize: 22, marginBottom: 4 } },
      "Stage 7 Button List",
    ),
    ...[
      "Import full App module only",
      "Render full App inside ErrorBoundary",
      "Render minimal Driver shell",
      "Run session restore only",
      "Run auth/me only",
      "Load normal Driver App",
    ].map((label) =>
      React.createElement(
        Pressable,
        {
          key: label,
          onPress: () => {},
          style: {
            backgroundColor: "#ffffff",
            borderColor: "#d7dce5",
            borderWidth: 1,
            padding: 14,
          },
        },
        React.createElement(
          Text,
          { style: { color: "#151922", fontSize: 16 } },
          label,
        ),
      ),
    ),
  );
}

AppRegistry.registerComponent("main", () => PrimitiveUiDriverApp);
