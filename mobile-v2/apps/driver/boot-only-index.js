import React from "react";
import { AppRegistry, Text, View } from "react-native";

const version = process.env.EXPO_PUBLIC_APP_VERSION ?? "2.0.0";
const buildNumber = process.env.IOS_BUILD_NUMBER ?? "200014";

function BootOnlyDriverApp() {
  return (
    <View
      style={{
        flex: 1,
        justifyContent: "center",
        padding: 24,
        backgroundColor: "#f7f8fb",
      }}
    >
      <Text style={{ color: "#151922", fontSize: 22, marginBottom: 8 }}>
        Ride 360 Driver V2
      </Text>
      <Text style={{ color: "#526070", fontSize: 16, marginBottom: 8 }}>
        Boot OK
      </Text>
      <Text style={{ color: "#697386", fontSize: 13, marginBottom: 4 }}>
        {version} ({buildNumber})
      </Text>
      <Text style={{ color: "#697386", fontSize: 13 }}>newArch=false</Text>
    </View>
  );
}

AppRegistry.registerComponent("main", () => BootOnlyDriverApp);
