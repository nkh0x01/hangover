export function driverHeaderTopPadding(
  os: string,
  statusBarHeight?: number | null,
): number {
  if (os === "ios") return 28;
  if (os === "android") return Math.max(statusBarHeight ?? 0, 0);
  return 0;
}
