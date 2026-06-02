import { useEffect, useState } from "react";
import { Pressable, TextInput, View } from "react-native";

import { createApiClient } from "@ride360/api";
import {
  createAuthClient,
  createAuthStore,
  createSecureTokenStorage,
  type Platform,
} from "@ride360/auth";
import { createRuntimeConfig } from "@ride360/config";
import { diagnosticsStore } from "@ride360/diagnostics";
import { Button, Card, ErrorState, LoadingState, Screen, Text } from "@ride360/ui";
import type { User } from "@ride360/types";

import {
  CUSTOMER_OTP_PURPOSE,
  blankCustomerHomeForm,
  createCustomerClient,
  customerErrorFrom,
  type CustomerHomeError,
  type CustomerHomeForm,
  type CustomerScreen,
  type FareEstimate,
  type Ride,
  routeAfterCustomerSession,
} from "./customer-flow";

const APP_VERSION = "0.1.0";
const config = createRuntimeConfig({
  appName: "Ride 360 Customer V2",
  appRole: "customer",
});

const storage = createSecureTokenStorage();
const authStore = createAuthStore();
const api = createApiClient({
  config,
  diagnostics: diagnosticsStore,
  getToken: async () => (await storage.read())?.token ?? null,
  defaultHeaders: {
    "X-App-Version": APP_VERSION,
    "X-Platform": currentPlatform(),
    "Accept-Language": "ka",
  },
});
const auth = createAuthClient({ api, storage, store: authStore, diagnostics: diagnosticsStore });
const customerClient = createCustomerClient(api);

type AppState = {
  screen: CustomerScreen;
  phone: string;
  otp: string;
  loading: boolean;
  message?: string;
  user?: User;
};

export default function App() {
  const [state, setState] = useState<AppState>({
    screen: "welcome",
    phone: "",
    otp: "",
    loading: true,
  });

  useEffect(() => {
    void bootstrap();
  }, []);

  useEffect(() => {
    diagnosticsStore.recordCurrentRoute(state.screen);
  }, [state.screen]);

  async function bootstrap() {
    let persisted;
    try {
      persisted = await storage.read();
    } catch {
      await auth.clearSession();
      setState((current) => ({ ...current, loading: false, screen: "welcome" }));
      return;
    }

    if (!persisted) {
      setState((current) => ({ ...current, loading: false, screen: "welcome" }));
      return;
    }

    authStore.setSignedIn(persisted);

    try {
      const user = await customerClient.getAuthMe();
      const screen = routeAfterCustomerSession(user, persisted.abilities);
      if (screen === "role-mismatch") {
        await auth.clearSession();
        setState((current) => ({
          ...current,
          loading: false,
          screen: "welcome",
          message: "სესია განახლდა. შედით თავიდან მგზავრის რეჟიმისთვის.",
        }));
        return;
      }

      setState((current) => ({
        ...current,
        loading: false,
        user,
        screen,
      }));
    } catch {
      await auth.clearSession();
      setState((current) => ({
        ...current,
        loading: false,
        screen: "welcome",
        message: "სესიის ვადა ამოიწურა. შედით თავიდან.",
      }));
    }
  }

  async function requestOtp() {
    setState((current) => ({ ...current, loading: true, message: undefined }));
    try {
      await auth.requestOtp({ phone: state.phone, purpose: CUSTOMER_OTP_PURPOSE });
      setState((current) => ({
        ...current,
        loading: false,
        screen: "otp",
        message: "კოდი გამოგზავნილია.",
      }));
    } catch (error) {
      setState((current) => ({
        ...current,
        loading: false,
        message: customerErrorFrom(error).message,
      }));
    }
  }

  async function verifyOtp() {
    setState((current) => ({ ...current, loading: true, message: undefined }));
    try {
      const session = await auth.verifyOtp({
        phone: state.phone,
        code: state.otp,
        purpose: CUSTOMER_OTP_PURPOSE,
        platform: currentPlatform(),
        app_version: APP_VERSION,
      });
      const user = await customerClient.getAuthMe();
      const abilities = session.status === "signedIn" ? session.abilities : [];
      setState((current) => ({
        ...current,
        loading: false,
        user,
        screen: routeAfterCustomerSession(user, abilities),
      }));
    } catch (error) {
      setState((current) => ({
        ...current,
        loading: false,
        message: customerErrorFrom(error).message,
      }));
    }
  }

  async function clearSession(message?: string) {
    await auth.clearSession();
    setState((current) => ({
      ...current,
      screen: "welcome",
      phone: "",
      otp: "",
      user: undefined,
      loading: false,
      message,
    }));
  }

  const diagnostics = useDiagnosticsSnapshot(state.screen);

  if (state.loading && state.screen === "welcome") {
    return (
      <Screen>
        <BuildLabel />
        <LoadingState label="იტვირთება" />
      </Screen>
    );
  }

  if (state.screen === "phone") {
    return (
      <PhoneScreen
        phone={state.phone}
        loading={state.loading}
        message={state.message}
        onPhoneChange={(phone) => setState((current) => ({ ...current, phone }))}
        onSubmit={requestOtp}
        onBack={() => setState((current) => ({ ...current, screen: "welcome" }))}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
      />
    );
  }

  if (state.screen === "otp") {
    return (
      <OtpScreen
        otp={state.otp}
        loading={state.loading}
        message={state.message}
        onOtpChange={(otp) => setState((current) => ({ ...current, otp }))}
        onSubmit={verifyOtp}
        onBack={() => setState((current) => ({ ...current, screen: "phone" }))}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
      />
    );
  }

  if (state.screen === "diagnostics") {
    return (
      <DiagnosticsScreen
        diagnostics={diagnostics}
        onBack={() => setState((current) => ({ ...current, screen: "welcome" }))}
      />
    );
  }

  if (state.screen === "role-mismatch") {
    return (
      <RoleMismatchScreen
        onClearSession={() => void clearSession("სესია გასუფთავდა.")}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
      />
    );
  }

  if (state.screen === "home") {
    return (
      <CustomerHomeScreen
        user={state.user}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() => void clearSession()}
      />
    );
  }

  return (
    <WelcomeScreen
      message={state.message}
      onLogin={() => setState((current) => ({ ...current, screen: "phone" }))}
      onDiagnostics={() =>
        setState((current) => ({ ...current, screen: "diagnostics" }))
      }
    />
  );
}

function WelcomeScreen({
  message,
  onLogin,
  onDiagnostics,
}: {
  message?: string;
  onLogin: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">Ride 360 Customer V2</Text>
      {message ? <Text>{message}</Text> : null}
      <Card>
        <Text variant="subtitle">შესვლა</Text>
        <Text variant="caption">მგზავრის ანგარიშში შესვლა ტელეფონის კოდით</Text>
      </Card>
      <Button onPress={onLogin}>ტელეფონით შესვლა</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
    </Screen>
  );
}

function PhoneScreen(props: {
  phone: string;
  loading: boolean;
  message?: string;
  onPhoneChange: (phone: string) => void;
  onSubmit: () => void;
  onBack: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">ტელეფონის ნომერი</Text>
      <Field label="ტელეფონი" value={props.phone} onChangeText={props.onPhoneChange} placeholder="5XXXXXXXX" />
      {props.message ? <Text>{props.message}</Text> : null}
      <Button disabled={props.loading} onPress={props.onSubmit}>კოდის მიღება</Button>
      <Button onPress={props.onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="უკან" onPress={props.onBack} />
    </Screen>
  );
}

function OtpScreen(props: {
  otp: string;
  loading: boolean;
  message?: string;
  onOtpChange: (otp: string) => void;
  onSubmit: () => void;
  onBack: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">SMS კოდი</Text>
      <Field label="კოდი" value={props.otp} onChangeText={props.onOtpChange} placeholder="000000" />
      {props.message ? <Text>{props.message}</Text> : null}
      <Button disabled={props.loading} onPress={props.onSubmit}>დადასტურება</Button>
      <Button onPress={props.onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="უკან" onPress={props.onBack} />
    </Screen>
  );
}

function CustomerHomeScreen({
  user,
  onDiagnostics,
  onClearSession,
}: {
  user?: User;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  const [form, setForm] = useState<CustomerHomeForm>(blankCustomerHomeForm);
  const [estimate, setEstimate] = useState<FareEstimate | null>(null);
  const [activeRide, setActiveRide] = useState<Ride | null>(null);
  const [history, setHistory] = useState<Ride[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<CustomerHomeError | null>(null);
  const [message, setMessage] = useState<string | undefined>();

  useEffect(() => {
    void customerClient.getActiveRide().then(setActiveRide).catch(() => undefined);
    void customerClient.getRideHistory().then(setHistory).catch(() => undefined);
  }, []);

  function updateField<K extends keyof CustomerHomeForm>(key: K, value: CustomerHomeForm[K]) {
    setForm((current) => ({ ...current, [key]: value }));
    setError(null);
  }

  async function estimateFare() {
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const next = await customerClient.estimateFare(form);
      setEstimate(next);
      setMessage("ფასი დათვლილია.");
    } catch (estimateError) {
      setError(customerErrorFrom(estimateError));
    } finally {
      setLoading(false);
    }
  }

  async function requestRide() {
    if (!estimate) {
      setError({
        title: "ჯერ ფასი დაითვალეთ",
        message: "მგზავრობის მოთხოვნამდე დააჭირეთ ფასის დათვლას.",
        kind: "validation",
      });
      return;
    }
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const ride = await customerClient.requestRide(form, estimate.id);
      setActiveRide(ride);
      setMessage("მგზავრობა მოთხოვნილია.");
    } catch (rideError) {
      setError(customerErrorFrom(rideError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">მგზავრის მთავარი</Text>
      <Card>
        <Text variant="caption">ანგარიში: {user?.phone ?? "-"}</Text>
        <Field label="აყვანის მისამართი" value={form.pickupAddress} onChangeText={(value) => updateField("pickupAddress", value)} />
        <Field label="Pickup lat" value={form.pickupLat} onChangeText={(value) => updateField("pickupLat", value)} />
        <Field label="Pickup lng" value={form.pickupLng} onChangeText={(value) => updateField("pickupLng", value)} />
        <Field label="დანიშნულების მისამართი" value={form.dropoffAddress} onChangeText={(value) => updateField("dropoffAddress", value)} />
        <Field label="Dropoff lat" value={form.dropoffLat} onChangeText={(value) => updateField("dropoffLat", value)} />
        <Field label="Dropoff lng" value={form.dropoffLng} onChangeText={(value) => updateField("dropoffLng", value)} />
        <Field label="ტრანსპორტი" value={form.vehicleType} onChangeText={(value) => updateField("vehicleType", value)} />
      </Card>
      {estimate ? (
        <Card>
          <Text variant="subtitle">
            შეფასება: {estimate.total_amount} {estimate.currency}
          </Text>
          <Text variant="caption">
            {estimate.distance_km} კმ · {estimate.duration_min} წთ
          </Text>
        </Card>
      ) : null}
      {activeRide ? (
        <Card>
          <Text variant="subtitle">აქტიური მგზავრობა</Text>
          <Text variant="caption">{activeRide.status ?? "მოთხოვნილი"}</Text>
        </Card>
      ) : (
        <Card><Text>აქტიური მგზავრობა არ არის.</Text></Card>
      )}
      <Card>
        <Text variant="subtitle">ისტორია</Text>
        <Text variant="caption">{history.length} ჩანაწერი</Text>
      </Card>
      {message ? <Text>{message}</Text> : null}
      {error ? (
        <ErrorState
          title={error.title}
          message={`${error.message}${error.requestId ? ` · request_id: ${error.requestId}` : ""}`}
        />
      ) : null}
      <Button disabled={loading} onPress={estimateFare}>ფასის დათვლა</Button>
      <Button disabled={loading} onPress={requestRide}>მგზავრობის მოთხოვნა</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function RoleMismatchScreen({
  onClearSession,
  onDiagnostics,
}: {
  onClearSession: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">სესია ვერ გამოიყენება</Text>
      <Card>
        <Text>
          ამ შესვლაზე backend-მა მგზავრის რეჟიმის უფლება არ დააბრუნა. ეს თქვენი
          ანგარიშის პრობლემა არ არის; სესია გაასუფთავეთ და თავიდან შედით.
        </Text>
      </Card>
      <Button onPress={onClearSession}>სესიის გასუფთავება</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
    </Screen>
  );
}

function DiagnosticsScreen({
  diagnostics,
  onBack,
}: {
  diagnostics: ReturnType<typeof diagnosticsStore.getState>;
  onBack: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">დიაგნოსტიკა</Text>
      <Card>
        <Text variant="caption">ეკრანი: {diagnostics.currentRoute ?? "-"}</Text>
        <Text variant="caption">
          ბოლო მოთხოვნა: {diagnostics.lastRequestMethod ?? "-"} {diagnostics.lastRequestUrl ?? "-"}
        </Text>
        <Text variant="caption">სტატუსი: {diagnostics.lastStatus ?? "-"}</Text>
        <Text variant="caption">token present: {String(diagnostics.tokenPresent ?? false)}</Text>
        <Text variant="caption">abilities: {diagnostics.tokenAbilities.join(", ") || "-"}</Text>
        <Text variant="caption">user type: {diagnostics.authUserType ?? "-"}</Text>
        <Text variant="caption">body: {diagnostics.lastBodyExcerpt ?? "-"}</Text>
        <Text variant="caption">network: {diagnostics.lastNetworkException ?? "-"}</Text>
      </Card>
      <Button onPress={onBack}>დაბრუნება</Button>
    </Screen>
  );
}

function Field({
  label,
  value,
  onChangeText,
  placeholder,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
}) {
  return (
    <View style={{ gap: 5 }}>
      <Text variant="caption">{label}</Text>
      <TextInput
        accessibilityLabel={label}
        placeholder={placeholder}
        value={value}
        onChangeText={onChangeText}
        style={inputStyle}
      />
    </View>
  );
}

function InlineLink({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress}>
      <Text style={{ color: "#1557d8", fontWeight: "700" }}>{label}</Text>
    </Pressable>
  );
}

function BuildLabel() {
  return (
    <Text variant="caption">
      Customer V2 · {APP_VERSION} · {config.APP_ENV}
    </Text>
  );
}

function useDiagnosticsSnapshot(route: CustomerScreen) {
  const [snapshot, setSnapshot] = useState(diagnosticsStore.getState());

  useEffect(() => {
    setSnapshot(diagnosticsStore.getState());
    return diagnosticsStore.subscribe(setSnapshot);
  }, [route]);

  return snapshot;
}

function currentPlatform(): Platform {
  const env = globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
  };
  const os = env.process?.env?.EXPO_OS;
  if (os === "ios" || os === "android" || os === "web") return os;
  return "web";
}

const inputStyle = {
  backgroundColor: "#ffffff",
  borderColor: "#d7dce5",
  borderRadius: 8,
  borderWidth: 1,
  color: "#151922",
  fontSize: 16,
  minHeight: 48,
  paddingHorizontal: 12,
  paddingVertical: 10,
};
