import type { PropsWithChildren, ReactNode } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text as NativeText,
  View,
  type PressableProps,
  type TextProps as NativeTextProps,
  type ViewProps,
} from "react-native";

import { textSelectableProp } from "./text-defaults";

export function Screen({ children, style, ...props }: PropsWithChildren<ViewProps>) {
  return (
    <ScrollView
      contentInsetAdjustmentBehavior="automatic"
      style={[{ flex: 1, backgroundColor: "#f7f8fb" }, style]}
      contentContainerStyle={{ flexGrow: 1, padding: 24, gap: 16 }}
      {...props}
    >
      {children}
    </ScrollView>
  );
}

export function Text({
  children,
  selectable,
  style,
  variant = "body",
  ...props
}: NativeTextProps & {
  variant?: "title" | "subtitle" | "body" | "caption";
}) {
  const variantStyle = {
    title: { fontSize: 28, fontWeight: "700" as const, color: "#151922" },
    subtitle: { fontSize: 18, fontWeight: "600" as const, color: "#2d3340" },
    body: { fontSize: 16, color: "#384152" },
    caption: { fontSize: 13, color: "#697386" },
  }[variant];

  return (
    <NativeText
      style={[variantStyle, style]}
      {...textSelectableProp(selectable)}
      {...props}
    >
      {children}
    </NativeText>
  );
}

export function Card({ children, style, ...props }: PropsWithChildren<ViewProps>) {
  return (
    <View
      style={[
        {
          backgroundColor: "#ffffff",
          borderColor: "#e4e7ee",
          borderRadius: 8,
          borderWidth: 1,
          gap: 10,
          padding: 16,
        },
        style,
      ]}
      {...props}
    >
      {children}
    </View>
  );
}

export function Button({
  children,
  disabled,
  style,
  ...props
}: PressableProps & { children: ReactNode }) {
  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled}
      style={({ pressed }) => [
        {
          alignItems: "center",
          backgroundColor: disabled ? "#aab2c0" : pressed ? "#103c8f" : "#1557d8",
          borderRadius: 8,
          minHeight: 48,
          justifyContent: "center",
          paddingHorizontal: 16,
          paddingVertical: 12,
        },
        typeof style === "function" ? style({ pressed }) : style,
      ]}
      {...props}
    >
      <Text style={{ color: "#ffffff", fontWeight: "700" }}>{children}</Text>
    </Pressable>
  );
}

export function LoadingState({ label = "Loading" }: { label?: string }) {
  return (
    <Card style={{ alignItems: "center" }}>
      <ActivityIndicator />
      <Text variant="caption">{label}</Text>
    </Card>
  );
}

export function ErrorState({
  title = "Something went wrong",
  message,
}: {
  title?: string;
  message?: string;
}) {
  return (
    <Card style={{ borderColor: "#efb4b4", backgroundColor: "#fff8f8" }}>
      <Text variant="subtitle">{title}</Text>
      {message ? <Text>{message}</Text> : null}
    </Card>
  );
}
