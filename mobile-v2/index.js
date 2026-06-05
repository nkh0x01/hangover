import { registerRootComponent } from "expo";

const appRole = process.env.EXPO_PUBLIC_APP_ROLE;
const driverDiagnosticMode =
  process.env.EXPO_PUBLIC_DRIVER_DIAGNOSTIC_MODE ??
  process.env.EXPO_DRIVER_DIAGNOSTIC_MODE ??
  "full";

if (appRole === "driver" && driverDiagnosticMode !== "full") {
  switch (driverDiagnosticMode) {
    case "cleanroom":
      require("./apps/driver/boot-only-index");
      break;
    case "null":
      require("./apps/driver/null-render-index");
      break;
    case "primitive":
      require("./apps/driver/primitive-ui-index");
      break;
    case "staged":
      require("./apps/driver/diagnostic-index");
      break;
    default:
      throw new Error(
        `Unsupported EXPO_DRIVER_DIAGNOSTIC_MODE "${driverDiagnosticMode}". Use full, cleanroom, null, primitive, or staged.`,
      );
  }
} else {
  const App =
    appRole === "driver"
      ? require("./apps/driver/src/App").default
      : require("./apps/customer/src/App").default;

  registerRootComponent(App);
}
