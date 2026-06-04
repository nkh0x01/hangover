import { registerRootComponent } from "expo";

const App =
  process.env.EXPO_PUBLIC_APP_ROLE === "driver"
    ? require("./apps/driver/src/App").default
    : require("./apps/customer/src/App").default;

registerRootComponent(App);
