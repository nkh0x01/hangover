import type { DriverScreen } from "./driver-flow";

export type StartupScreenState = {
  loading: boolean;
  screen: DriverScreen;
};

export function shouldRenderNativeStartupScreen(
  state: StartupScreenState,
): boolean {
  return state.loading && state.screen === "welcome";
}
