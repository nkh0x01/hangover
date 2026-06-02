import { registerRootComponent } from "expo";

import CustomerApp from "./apps/customer/src/App";
import DriverApp from "./apps/driver/src/App";

const App = process.env.EXPO_APP_TARGET === "driver" ? DriverApp : CustomerApp;

registerRootComponent(App);
