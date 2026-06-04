import { Component, useEffect, useState, type ErrorInfo, type ReactNode } from "react";
import {
  Platform as NativePlatform,
  Pressable,
  Text as NativeText,
  TextInput,
  View,
  type KeyboardTypeOptions,
} from "react-native";

import { ApiError, createApiClient } from "@ride360/api";
import {
  createAuthClient,
  createAuthStore,
  createSecureTokenStorage,
  type Platform,
} from "@ride360/auth";
import { createRuntimeConfig } from "@ride360/config";
import { diagnosticsStore } from "@ride360/diagnostics";
import { Button, Card, ErrorState, LoadingState, Screen, Text } from "@ride360/ui";
import type { DriverContext, User } from "@ride360/types";

import {
  DRIVER_OTP_PURPOSE,
  WELCOME_ACTIONS,
  type AuthMode,
  type DriverScreen,
  canContinueApplication,
  driverStatusText,
  onboardingPrimaryLabel,
  routeAfterDriverMe,
  stateForWelcomeAction,
  shouldClearSessionOnStartupError,
} from "./driver-flow";
import {
  APPLICATION_STEPS,
  blankApplicationForm,
  createDriverApplicationClient,
  fieldErrorsFromApiError,
  formFromApplication,
  isApplicationReadOnly,
  shouldRouteApprovedToDashboard,
  statusTitle,
  validateApplicationForm,
  type ApplicationFieldErrors,
  type DriverApplication,
  type DriverApplicationForm,
} from "./driver-application";
import {
  createDriverDashboardClient,
  dashboardBlockReason,
  dashboardErrorFrom,
  nextRideActionLabel,
  rideStatusText,
  shiftStatusFromContext,
  type ActiveRideOffer,
  type DashboardError,
  type DriverRide,
  type LocationPoint,
  type ShiftStatus,
} from "./driver-dashboard";

const APP_VERSION = readPublicEnv("EXPO_PUBLIC_APP_VERSION") ?? "0.1.0";
const config = createRuntimeConfig({
  appName: "Ride 360 Driver V2",
  appRole: "driver",
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
const auth = createAuthClient({
  api,
  storage,
  store: authStore,
  diagnostics: diagnosticsStore,
});
const applicationClient = createDriverApplicationClient(api);
const dashboardClient = createDriverDashboardClient(api);

type ScreenState = {
  screen: DriverScreen;
  mode: AuthMode;
  phone: string;
  otp: string;
  loading: boolean;
  message?: string;
  user?: User;
  startupError?: string;
};

type ErrorBoundaryState = {
  cleared: boolean;
  componentStack?: string;
  error?: Error;
};

export default function App() {
  return (
    <DriverErrorBoundary>
      <DriverApp />
    </DriverErrorBoundary>
  );
}

function DriverApp() {
  const [state, setState] = useState<ScreenState>({
    screen: "welcome",
    mode: "login",
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
      await clearStoredSessionSafely();
      setState((current) => ({
        ...current,
        screen: "welcome",
        loading: false,
        message: "სესიის წაკითხვა ვერ მოხერხდა. შედით თავიდან.",
      }));
      return;
    }

    if (!persisted) {
      setState((current) => ({ ...current, screen: "welcome", loading: false }));
      return;
    }

    authStore.setSignedIn(persisted);

    try {
      const user = await auth.getCurrentUser();
      setState((current) => ({
        ...current,
        screen: routeAfterDriverMe(user, "login", "startup"),
        user,
        loading: false,
      }));
    } catch (error) {
      if (shouldClearSessionOnStartupError(error)) {
        await clearStoredSessionSafely();
        setState((current) => ({
          ...current,
          screen: "welcome",
          loading: false,
          message: "სესიის ვადა ამოიწურა. გთხოვთ შეხვიდეთ თავიდან.",
        }));
        return;
      }

      setState((current) => ({
        ...current,
        screen: "startup-error",
        loading: false,
        startupError: driverErrorMessage(error),
      }));
    }
  }

  function openWelcomeAction(action: AuthMode | "diagnostics") {
    const next = stateForWelcomeAction(action, state.mode);
    setState((current) => ({
      ...current,
      mode: next.mode,
      screen: next.screen,
      message: undefined,
    }));
  }

  async function requestOtp() {
    setState((current) => ({ ...current, loading: true, message: undefined }));
    try {
      await auth.requestOtp({ phone: state.phone, purpose: DRIVER_OTP_PURPOSE });
      setState((current) => ({
        ...current,
        screen: "otp",
        loading: false,
        message: "კოდი გამოგზავნილია.",
      }));
    } catch (error) {
      setState((current) => ({
        ...current,
        loading: false,
        message: driverErrorMessage(error),
      }));
    }
  }

  async function verifyOtp() {
    setState((current) => ({ ...current, loading: true, message: undefined }));
    try {
      await auth.verifyOtp({
        phone: state.phone,
        code: state.otp,
        purpose: DRIVER_OTP_PURPOSE,
        platform: currentPlatform(),
        app_version: APP_VERSION,
      });
      const user = await auth.getCurrentUser();
      setState((current) => ({
        ...current,
        screen: routeAfterDriverMe(user, current.mode, "post-auth"),
        user,
        loading: false,
      }));
    } catch (error) {
      setState((current) => ({
        ...current,
        loading: false,
        message: driverErrorMessage(error),
      }));
    }
  }

  async function clearSessionToWelcome(message?: string) {
    await clearStoredSessionSafely();
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
        mode={state.mode}
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
        mode={state.mode}
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

  if (state.screen === "startup-error") {
    return (
      <StartupErrorScreen
        message={state.startupError ?? "ქსელის ან სერვერის შეცდომა."}
        diagnostics={diagnostics}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() =>
          void clearSessionToWelcome("სესია გასუფთავდა. შეგიძლიათ შეხვიდეთ თავიდან.")
        }
      />
    );
  }

  if (state.screen === "role-mismatch") {
    return (
      <RoleMismatchScreen
        onRegister={() =>
          setState((current) => ({
            ...current,
            mode: "registration",
            screen: "phone",
            message: undefined,
          }))
        }
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() => void clearSessionToWelcome()}
      />
    );
  }

  if (state.screen === "application") {
    return (
      <ApplicationScreen
        context={state.user?.driver_context}
        fallbackPhone={state.user?.phone}
        onApproved={() =>
          setState((current) => ({ ...current, screen: "dashboard" }))
        }
        onStatus={() =>
          setState((current) => ({ ...current, screen: "onboarding-status" }))
        }
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() => void clearSessionToWelcome()}
      />
    );
  }

  if (state.screen === "dashboard") {
    return (
      <DashboardScreen
        user={state.user}
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() => void clearSessionToWelcome()}
      />
    );
  }

  if (state.screen === "onboarding-status") {
    return (
      <OnboardingStatusScreen
        context={state.user?.driver_context}
        onContinue={() =>
          setState((current) => ({
            ...current,
            screen:
              current.user?.driver_context &&
              canContinueApplication(current.user.driver_context)
                ? "application"
                : "onboarding-status",
          }))
        }
        onDiagnostics={() =>
          setState((current) => ({ ...current, screen: "diagnostics" }))
        }
        onClearSession={() => void clearSessionToWelcome()}
      />
    );
  }

  return (
    <WelcomeScreen
      message={state.message}
      onLogin={() => openWelcomeAction("login")}
      onRegister={() => openWelcomeAction("registration")}
      onDiagnostics={() => openWelcomeAction("diagnostics")}
    />
  );
}

class DriverErrorBoundary extends Component<
  { children: ReactNode },
  ErrorBoundaryState
> {
  state: ErrorBoundaryState = { cleared: false };

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { cleared: false, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    this.setState((current) => ({
      ...current,
      componentStack: info.componentStack ?? undefined,
    }));
    diagnosticsStore.recordError({
      code: "driver.root.render",
      message: error.message,
      details: { componentStack: info.componentStack },
      bodyExcerpt: stackExcerpt(error, info.componentStack ?? undefined),
    });
  }

  clearSession = async () => {
    await clearStoredSessionSafely();
    this.setState((current) => ({ ...current, cleared: true }));
  };

  render() {
    if (this.state.error) {
      return (
        <StartupCrashScreen
          cleared={this.state.cleared}
          error={this.state.error}
          stack={stackExcerpt(this.state.error, this.state.componentStack)}
          onClearSession={this.clearSession}
        />
      );
    }

    return this.props.children;
  }
}

function StartupCrashScreen({
  cleared,
  error,
  stack,
  onClearSession,
}: {
  cleared: boolean;
  error: Error;
  stack: string;
  onClearSession: () => void;
}) {
  const diagnostics = diagnosticsStore.getState();

  return (
    <View
      style={{
        backgroundColor: "#f7f8fb",
        flex: 1,
        gap: 12,
        justifyContent: "center",
        padding: 24,
      }}
    >
      <NativeText style={{ color: "#151922", fontSize: 22, fontWeight: "700" }}>
        Ride 360 Driver V2
      </NativeText>
      <NativeText style={{ color: "#526070", fontSize: 13 }}>
        Driver V2 · {APP_VERSION} · {config.APP_ENV}
      </NativeText>
      <NativeText style={{ color: "#151922", fontSize: 16, fontWeight: "700" }}>
        აპის გაშვების შეცდომა
      </NativeText>
      <NativeText style={{ color: "#526070", fontSize: 14 }}>
        {error.message || "გაუთვალისწინებელი შეცდომა."}
      </NativeText>
      <NativeText style={{ color: "#526070", fontSize: 12 }}>
        ეკრანი: {diagnostics.currentRoute ?? "-"}
      </NativeText>
      <NativeText style={{ color: "#526070", fontSize: 12 }}>
        ბოლო მოთხოვნა: {diagnostics.lastRequestMethod ?? "-"}{" "}
        {diagnostics.lastRequestUrl ?? "-"}
      </NativeText>
      <NativeText style={{ color: "#526070", fontSize: 12 }}>
        stack: {stack || "-"}
      </NativeText>
      {cleared ? (
        <NativeText style={{ color: "#1557d8", fontSize: 13 }}>
          სესია გასუფთავდა. დახურეთ და თავიდან გახსენით აპი.
        </NativeText>
      ) : null}
      <Pressable
        accessibilityRole="button"
        onPress={onClearSession}
        style={{
          alignItems: "center",
          backgroundColor: "#1557d8",
          borderRadius: 8,
          minHeight: 48,
          justifyContent: "center",
          paddingHorizontal: 16,
        }}
      >
        <NativeText style={{ color: "#ffffff", fontWeight: "700" }}>
          სესიის გასუფთავება
        </NativeText>
      </Pressable>
    </View>
  );
}

function WelcomeScreen({
  message,
  onLogin,
  onRegister,
  onDiagnostics,
}: {
  message?: string;
  onLogin: () => void;
  onRegister: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">Ride 360 Driver V2</Text>
      {message ? <Text>{message}</Text> : null}
      <ActionCard
        title={WELCOME_ACTIONS[0].title}
        subtitle={WELCOME_ACTIONS[0].subtitle}
        onPress={onLogin}
      />
      <ActionCard
        title={WELCOME_ACTIONS[1].title}
        subtitle={WELCOME_ACTIONS[1].subtitle}
        onPress={onRegister}
      />
      <Button onPress={onDiagnostics}>{WELCOME_ACTIONS[2].title}</Button>
    </Screen>
  );
}

function PhoneScreen(props: {
  mode: AuthMode;
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
      <Text variant="title">
        {props.mode === "login" ? "შესვლა" : "მძღოლად რეგისტრაცია"}
      </Text>
      <Text>
        შეიყვანეთ ტელეფონის ნომერი. ორივე რეჟიმი დროებით იყენებს
        driver-capable OTP purpose-ს.
      </Text>
      <TextInput
        accessibilityLabel="ტელეფონი"
        inputMode="tel"
        placeholder="5XXXXXXXX"
        value={props.phone}
        onChangeText={props.onPhoneChange}
        style={inputStyle}
      />
      {props.message ? <Text>{props.message}</Text> : null}
      <Button disabled={props.loading} onPress={props.onSubmit}>
        კოდის მიღება
      </Button>
      <SecondaryActions
        onBack={props.onBack}
        onDiagnostics={props.onDiagnostics}
      />
    </Screen>
  );
}

function OtpScreen(props: {
  mode: AuthMode;
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
      <Text variant="title">
        {props.mode === "login" ? "შესვლის კოდი" : "რეგისტრაციის კოდი"}
      </Text>
      <TextInput
        accessibilityLabel="OTP"
        inputMode="numeric"
        maxLength={6}
        placeholder="000000"
        value={props.otp}
        onChangeText={props.onOtpChange}
        style={inputStyle}
      />
      {props.message ? <Text>{props.message}</Text> : null}
      <Button disabled={props.loading} onPress={props.onSubmit}>
        დადასტურება
      </Button>
      <SecondaryActions
        onBack={props.onBack}
        onDiagnostics={props.onDiagnostics}
      />
    </Screen>
  );
}

function OnboardingStatusScreen({
  context,
  onContinue,
  onDiagnostics,
  onClearSession,
}: {
  context?: DriverContext;
  onContinue: () => void;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">მძღოლის განაცხადი</Text>
      <Card>
        <Text>{driverStatusText(context)}</Text>
        <Text variant="caption">
          სტატუსი: {context?.application_status ?? "არ არის დაწყებული"}
        </Text>
        <Text variant="caption">
          მიზეზი: {context?.reason_if_cannot_go_online ?? "არ არის"}
        </Text>
      </Card>
      <Button onPress={onContinue}>{onboardingPrimaryLabel(context)}</Button>
      <Button onPress={onClearSession}>შესვლა სხვა ნომრით</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function ApplicationScreen({
  context,
  fallbackPhone,
  onApproved,
  onStatus,
  onDiagnostics,
  onClearSession,
}: {
  context?: DriverContext;
  fallbackPhone?: string | null;
  onApproved: () => void;
  onStatus: () => void;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  const [step, setStep] = useState(0);
  const [application, setApplication] = useState<DriverApplication | null>(null);
  const [form, setForm] = useState<DriverApplicationForm>(blankApplicationForm);
  const [errors, setErrors] = useState<ApplicationFieldErrors>({});
  const [screenContext, setScreenContext] = useState<DriverContext | undefined>(
    context,
  );
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | undefined>();

  useEffect(() => {
    let mounted = true;
    async function loadApplication() {
      setLoading(true);
      setMessage(undefined);
      try {
        const response = await applicationClient.getApplication();
        if (!mounted) return;
        if (shouldRouteApprovedToDashboard(response.data, response.driver_context)) {
          onApproved();
          return;
        }
        setApplication(response.data);
        setScreenContext(response.driver_context);
        setForm(formFromApplication(response.data, fallbackPhone));
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
          setApplication(null);
          setForm(formFromApplication(null, fallbackPhone));
        } else {
          setMessage(driverErrorMessage(error));
        }
      } finally {
        if (mounted) setLoading(false);
      }
    }

    void loadApplication();
    return () => {
      mounted = false;
    };
  }, [fallbackPhone]);

  const readOnly = isApplicationReadOnly(application, screenContext);

  function updateField<K extends keyof DriverApplicationForm>(
    key: K,
    value: DriverApplicationForm[K],
  ) {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: undefined }));
  }

  async function saveDraft() {
    setSaving(true);
    setMessage(undefined);
    try {
      const response = await applicationClient.saveDraft(form, application?.id);
      if (shouldRouteApprovedToDashboard(response.data, response.driver_context)) {
        onApproved();
        return;
      }
      setApplication(response.data);
      setScreenContext(response.driver_context);
      setErrors({});
      setMessage("შენახულია.");
    } catch (error) {
      const apiErrors = fieldErrorsFromApiError(error);
      if (Object.keys(apiErrors).length > 0) setErrors(apiErrors);
      setMessage(driverErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function submitApplication() {
    const nextErrors = validateApplicationForm(form);
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      setStep(0);
      setMessage("გთხოვთ შეავსოთ სავალდებულო ველები.");
      return;
    }

    setSaving(true);
    setMessage(undefined);
    try {
      const saved = await applicationClient.saveDraft(form, application?.id);
      const submitted = await applicationClient.submit();
      const latestApplication = submitted.data ?? saved.data;
      if (shouldRouteApprovedToDashboard(latestApplication, submitted.driver_context)) {
        onApproved();
        return;
      }
      setApplication(latestApplication);
      setScreenContext(submitted.driver_context);
      setMessage("განაცხადი გაგზავნილია");
      setStep(3);
    } catch (error) {
      const apiErrors = fieldErrorsFromApiError(error);
      if (Object.keys(apiErrors).length > 0) setErrors(apiErrors);
      setMessage(driverErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <Screen>
        <BuildLabel />
        <LoadingState label="განაცხადი იტვირთება" />
        <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      </Screen>
    );
  }

  if (readOnly || application?.status === "rejected") {
    return (
      <ApplicationStatusScreen
        application={application}
        context={screenContext}
        message={message}
        onEdit={
          application?.status === "rejected" || application?.status === "needs_changes"
            ? () => setApplication((current) => current && { ...current, status: "draft" })
            : undefined
        }
        onDiagnostics={onDiagnostics}
        onClearSession={onClearSession}
      />
    );
  }

  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">{statusTitle(application, screenContext)}</Text>
      <Progress step={step} />
      {message ? <Text>{message}</Text> : null}
      <Card>
        <Text variant="subtitle">{APPLICATION_STEPS[step]}</Text>
        {application?.status === "needs_changes" ? (
          <Text>{application.admin_note ?? "ადმინისტრატორმა მოითხოვა ცვლილებები."}</Text>
        ) : null}
        {step === 0 ? (
          <PersonalStep form={form} errors={errors} updateField={updateField} />
        ) : null}
        {step === 1 ? (
          <VehicleStep form={form} errors={errors} updateField={updateField} />
        ) : null}
        {step === 2 ? <DocumentsStep application={application} /> : null}
        {step === 3 ? (
          <ConfirmStep form={form} errors={errors} updateField={updateField} />
        ) : null}
      </Card>
      <View style={{ gap: 10 }}>
        {step > 0 ? (
          <Button onPress={() => setStep((current) => Math.max(0, current - 1))}>
            უკან
          </Button>
        ) : null}
        {step < APPLICATION_STEPS.length - 1 ? (
          <Button onPress={() => setStep((current) => current + 1)}>
            შემდეგი
          </Button>
        ) : (
          <Button disabled={saving} onPress={submitApplication}>
            გაგზავნა
          </Button>
        )}
        <Button disabled={saving} onPress={saveDraft}>
          მონახაზის შენახვა
        </Button>
      </View>
      <Button onPress={onStatus}>სტატუსზე დაბრუნება</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function PersonalStep({
  form,
  errors,
  updateField,
}: {
  form: DriverApplicationForm;
  errors: ApplicationFieldErrors;
  updateField: <K extends keyof DriverApplicationForm>(
    key: K,
    value: DriverApplicationForm[K],
  ) => void;
}) {
  return (
    <View style={{ gap: 10 }}>
      <Field label="სახელი" value={form.first_name} error={errors.first_name} onChangeText={(value) => updateField("first_name", value)} />
      <Field label="გვარი" value={form.last_name} error={errors.last_name} onChangeText={(value) => updateField("last_name", value)} />
      <Field label="პირადი ნომერი" value={form.personal_id} error={errors.personal_id} onChangeText={(value) => updateField("personal_id", value)} keyboard="numeric" />
      <Field label="ტელეფონი" value={form.phone_e164} error={errors.phone_e164} onChangeText={(value) => updateField("phone_e164", value)} keyboard="phone-pad" />
      <Field label="ელფოსტა" value={form.email} error={errors.email} onChangeText={(value) => updateField("email", value)} keyboard="email-address" />
      <Field label="დაბადების თარიღი" value={form.birth_date} error={errors.birth_date} onChangeText={(value) => updateField("birth_date", value)} placeholder="YYYY-MM-DD" />
      <Field label="მომსახურების ზონა" value={form.service_zone} error={errors.service_zone} onChangeText={(value) => updateField("service_zone", value)} />
    </View>
  );
}

function VehicleStep({
  form,
  errors,
  updateField,
}: {
  form: DriverApplicationForm;
  errors: ApplicationFieldErrors;
  updateField: <K extends keyof DriverApplicationForm>(
    key: K,
    value: DriverApplicationForm[K],
  ) => void;
}) {
  return (
    <View style={{ gap: 10 }}>
      <Field label="მძღოლის ტიპი" value={form.driver_type} error={errors.driver_type} onChangeText={(value) => updateField("driver_type", value)} placeholder="moto / car / courier" />
      <Field label="ტრანსპორტის ტიპი" value={form.vehicle_type} error={errors.vehicle_type} onChangeText={(value) => updateField("vehicle_type", value)} placeholder="scooter_petrol / car" />
      <Field label="ბრენდი" value={form.vehicle_brand} error={errors.vehicle_brand} onChangeText={(value) => updateField("vehicle_brand", value)} />
      <Field label="მოდელი" value={form.vehicle_model} error={errors.vehicle_model} onChangeText={(value) => updateField("vehicle_model", value)} />
      <Field label="წელი" value={form.vehicle_year} error={errors.vehicle_year} onChangeText={(value) => updateField("vehicle_year", value)} keyboard="numeric" />
      <Field label="ფერი" value={form.vehicle_color} error={errors.vehicle_color} onChangeText={(value) => updateField("vehicle_color", value)} />
      <Field label="სანომრე ნიშანი" value={form.vehicle_plate} error={errors.vehicle_plate} onChangeText={(value) => updateField("vehicle_plate", value)} />
      <Field label="ძრავის მოცულობა" value={form.engine_cc} error={errors.engine_cc} onChangeText={(value) => updateField("engine_cc", value)} />
      <Field label="დაზღვევის ვადა" value={form.insurance_expires_on} error={errors.insurance_expires_on} onChangeText={(value) => updateField("insurance_expires_on", value)} placeholder="YYYY-MM-DD" />
      <Field label="ტექდათვალიერების ვადა" value={form.inspection_expires_on} error={errors.inspection_expires_on} onChangeText={(value) => updateField("inspection_expires_on", value)} placeholder="YYYY-MM-DD" />
    </View>
  );
}

function DocumentsStep({
  application,
}: {
  application: DriverApplication | null;
}) {
  const documents = application?.documents ?? [];
  return (
    <View style={{ gap: 10 }}>
      <Text>
        დოკუმენტების ატვირთვის backend endpoint ხელმისაწვდომია:
        /driver/application/documents. ფაილის picker შემდეგ ეტაპზე დაემატება.
      </Text>
      {[
        "id_front",
        "id_back",
        "license_front",
        "license_back",
        "vehicle_registration",
        "vehicle_photo",
        "selfie",
        "insurance",
      ].map((type) => {
        const uploaded = documents.find((document) => document.doc_type === type);
        return (
          <Text key={type} variant="caption">
            {type}: {uploaded?.status ?? "ასატვირთია"}
          </Text>
        );
      })}
    </View>
  );
}

function ConfirmStep({
  form,
  errors,
  updateField,
}: {
  form: DriverApplicationForm;
  errors: ApplicationFieldErrors;
  updateField: <K extends keyof DriverApplicationForm>(
    key: K,
    value: DriverApplicationForm[K],
  ) => void;
}) {
  return (
    <View style={{ gap: 10 }}>
      <Toggle label="ინფორმაცია სწორია" value={form.information_confirmed} error={errors.information_confirmed} onPress={() => updateField("information_confirmed", !form.information_confirmed)} />
      <Toggle label="ვეთანხმები პირობებს" value={form.terms_accepted} error={errors.terms_accepted} onPress={() => updateField("terms_accepted", !form.terms_accepted)} />
      <Toggle label="ვეთანხმები კონფიდენციალურობას" value={form.privacy_accepted} error={errors.privacy_accepted} onPress={() => updateField("privacy_accepted", !form.privacy_accepted)} />
      <Text variant="caption">გაგზავნამდე შეგიძლიათ მონახაზის შენახვა.</Text>
    </View>
  );
}

function ApplicationStatusScreen({
  application,
  context,
  message,
  onEdit,
  onDiagnostics,
  onClearSession,
}: {
  application: DriverApplication | null;
  context?: DriverContext;
  message?: string;
  onEdit?: () => void;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">{statusTitle(application, context)}</Text>
      <Card>
        <Text>{message ?? driverStatusText(context)}</Text>
        {application?.admin_note ? <Text>ადმინისტრატორი: {application.admin_note}</Text> : null}
        {application?.rejection_reason ? <Text>მიზეზი: {application.rejection_reason}</Text> : null}
        {application?.status === "rejected" ? (
          <Text>თუ გადაწყვეტილება გაუგებარია, დაუკავშირდით მხარდაჭერას.</Text>
        ) : null}
      </Card>
      {onEdit ? <Button onPress={onEdit}>განაცხადის გაგრძელება</Button> : null}
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function Field({
  label,
  value,
  error,
  onChangeText,
  placeholder,
  keyboard,
}: {
  label: string;
  value: string;
  error?: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  keyboard?: "default" | "numeric" | "phone-pad" | "email-address";
}) {
  const inputMode =
    keyboard === "numeric" ? "numeric" : keyboard === "phone-pad" ? "tel" : keyboard === "email-address" ? "email" : "text";
  const keyboardType: KeyboardTypeOptions | undefined = keyboard;

  return (
    <View style={{ gap: 5 }}>
      <Text variant="caption">{label}</Text>
      <TextInput
        accessibilityLabel={label}
        inputMode={inputMode}
        keyboardType={keyboardType}
        placeholder={placeholder}
        value={value}
        onChangeText={onChangeText}
        style={inputStyle}
      />
      {error ? <Text variant="caption" style={{ color: "#b42318" }}>{error}</Text> : null}
    </View>
  );
}

function Toggle({
  label,
  value,
  error,
  onPress,
}: {
  label: string;
  value: boolean;
  error?: string;
  onPress: () => void;
}) {
  return (
    <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: value }} onPress={onPress}>
      <Card style={{ backgroundColor: value ? "#eef6ff" : "#ffffff" }}>
        <Text>{value ? "✓ " : ""}{label}</Text>
        {error ? <Text variant="caption" style={{ color: "#b42318" }}>{error}</Text> : null}
      </Card>
    </Pressable>
  );
}

function Progress({ step }: { step: number }) {
  return (
    <View style={{ gap: 6 }}>
      <Text variant="caption">
        ნაბიჯი {step + 1}/{APPLICATION_STEPS.length}
      </Text>
      <Text variant="caption">{APPLICATION_STEPS.join(" · ")}</Text>
    </View>
  );
}

function StartupErrorScreen({
  message,
  diagnostics,
  onDiagnostics,
  onClearSession,
}: {
  message: string;
  diagnostics: ReturnType<typeof diagnosticsStore.getState>;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <ErrorState title="გაშვების შეცდომა" message={message} />
      <Card>
        <Text variant="caption">
          ბოლო მოთხოვნა: {diagnostics.lastRequestMethod ?? "-"}{" "}
          {diagnostics.lastRequestUrl ?? "-"}
        </Text>
        <Text variant="caption">სტატუსი: {diagnostics.lastStatus ?? "-"}</Text>
      </Card>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <Button onPress={onClearSession}>სესიის გასუფთავება</Button>
    </Screen>
  );
}

function RoleMismatchScreen({
  onRegister,
  onDiagnostics,
  onClearSession,
}: {
  onRegister: () => void;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">მძღოლის ანგარიში საჭიროა</Text>
      <Card>
        <Text>
          ამ ნომრით მიღებული სესია მძღოლის უფლებას არ შეიცავს. შეგიძლიათ
          დაიწყოთ მძღოლად რეგისტრაცია.
        </Text>
      </Card>
      <Button onPress={onRegister}>მძღოლად რეგისტრაცია</Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function DashboardScreen({
  user,
  onDiagnostics,
  onClearSession,
}: {
  user?: User;
  onDiagnostics: () => void;
  onClearSession: () => void;
}) {
  const [dashboardUser, setDashboardUser] = useState<User | undefined>(user);
  const [shiftStatus, setShiftStatus] = useState<ShiftStatus>(
    shiftStatusFromContext(user?.driver_context),
  );
  const [loading, setLoading] = useState(false);
  const [dispatchRefreshing, setDispatchRefreshing] = useState(false);
  const [activeOffer, setActiveOffer] = useState<ActiveRideOffer | null>(null);
  const [activeRide, setActiveRide] = useState<DriverRide | null>(null);
  const [error, setError] = useState<DashboardError | null>(null);
  const [message, setMessage] = useState<string | undefined>();

  useEffect(() => {
    let mounted = true;
    async function loadDriver() {
      try {
        const latest = await dashboardClient.getDriverMe();
        if (!mounted) return;
        const latestShiftStatus = shiftStatusFromContext(latest.driver_context);
        setDashboardUser(latest);
        setShiftStatus(latestShiftStatus);
        void refreshDispatchState(false, latestShiftStatus);
      } catch (loadError) {
        if (!mounted) return;
        setError(dashboardErrorFrom(loadError));
      }
    }
    void loadDriver();
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (shiftStatus !== "online") {
      setActiveOffer(null);
      return;
    }

    let cancelled = false;
    async function pollDispatch() {
      if (cancelled) return;
      await refreshDispatchState(false, "online");
    }

    void pollDispatch();
    const timer = setInterval(() => {
      void pollDispatch();
    }, 5000);

    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [shiftStatus]);

  const context = dashboardUser?.driver_context;
  const blockReason = dashboardBlockReason(dashboardUser);

  async function refreshDispatchState(
    showSpinner = false,
    statusOverride: ShiftStatus = shiftStatus,
  ) {
    if (showSpinner) setDispatchRefreshing(true);
    try {
      const ride = await dashboardClient.getActiveRide();
      setActiveRide(ride);

      if (ride) {
        setActiveOffer(null);
        return;
      }

      if (statusOverride === "online") {
        setActiveOffer(await dashboardClient.getActiveOffer());
      } else {
        setActiveOffer(null);
      }
    } catch (dispatchError) {
      setError(dashboardErrorFrom(dispatchError));
    } finally {
      if (showSpinner) setDispatchRefreshing(false);
    }
  }

  async function startShift() {
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const location = await getCurrentLocation();
      const result = await dashboardClient.goOnline(location);
      const nextStatus = result.online ? "online" : "offline";
      setShiftStatus(nextStatus);
      setMessage(result.online ? "ცვლა დაწყებულია." : "სტატუსი განახლდა.");
      await refreshDispatchState(true, nextStatus);
    } catch (startError) {
      const mapped = dashboardErrorFrom(startError);
      setError(mapped);
      if (mapped.kind === "permission" && context?.reason_if_cannot_go_online) {
        setError({ ...mapped, message: context.reason_if_cannot_go_online });
      }
    } finally {
      setLoading(false);
    }
  }

  async function endShift() {
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const result = await dashboardClient.goOffline();
      setShiftStatus(result.online ? "online" : "offline");
      setActiveOffer(null);
      setActiveRide(null);
      setMessage("ცვლა დასრულებულია.");
    } catch (offlineError) {
      setError(dashboardErrorFrom(offlineError));
    } finally {
      setLoading(false);
    }
  }

  async function acceptOffer() {
    if (!activeOffer) return;
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const ride = await dashboardClient.acceptOffer(activeOffer.ride_ulid);
      setActiveRide(ride);
      setActiveOffer(null);
      setMessage("შეთავაზება მიღებულია.");
    } catch (offerError) {
      setError(dashboardErrorFrom(offerError));
    } finally {
      setLoading(false);
    }
  }

  async function rejectOffer() {
    if (!activeOffer) return;
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      await dashboardClient.rejectOffer(activeOffer.ride_ulid);
      setActiveOffer(null);
      setMessage("შეთავაზება უარყოფილია.");
      await refreshDispatchState(true, "online");
    } catch (offerError) {
      setError(dashboardErrorFrom(offerError));
    } finally {
      setLoading(false);
    }
  }

  async function advanceRide() {
    if (!activeRide) return;
    setLoading(true);
    setError(null);
    setMessage(undefined);
    try {
      const ride =
        activeRide.status === "accepted"
          ? await dashboardClient.markArriving(activeRide.id)
          : activeRide.status === "driver_arriving"
            ? await dashboardClient.markArrived(activeRide.id)
            : activeRide.status === "driver_arrived"
              ? await dashboardClient.startRide(activeRide.id)
              : activeRide.status === "in_progress"
                ? await dashboardClient.completeRide(activeRide.id)
                : activeRide;

      if (ride.status === "completed") {
        setActiveRide(null);
        setMessage("მგზავრობა დასრულებულია.");
      } else {
        setActiveRide(ride);
        setMessage(rideStatusText(ride.status));
      }
    } catch (rideError) {
      setError(dashboardErrorFrom(rideError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <Screen>
      <BuildLabel />
      <Text variant="title">მძღოლის დაფა</Text>
      <Card>
        <Text variant="subtitle">
          სტატუსი: {shiftStatus === "online" ? "ონლაინ" : "ოფლაინ"}
        </Text>
        <Text variant="caption">მომხმარებელი: {dashboardUser?.phone ?? "-"}</Text>
        <Text variant="caption">
          დღევანდელი შემოსავალი: {context?.today_earnings ?? "0.00"} GEL
        </Text>
        <Text variant="caption">
          ტრანსპორტი: #{context?.vehicle_id ?? "-"} · {context?.vehicle_status ?? "-"}
        </Text>
        {blockReason ? <Text>{blockReason}</Text> : null}
      </Card>
      <DispatchPanel
        activeOffer={activeOffer}
        activeRide={activeRide}
        loading={loading}
        refreshing={dispatchRefreshing}
        online={shiftStatus === "online"}
        onAccept={acceptOffer}
        onReject={rejectOffer}
        onAdvance={advanceRide}
        onRefresh={() => void refreshDispatchState(true)}
      />
      {message ? <Text>{message}</Text> : null}
      {error ? (
        <ErrorState
          title={error.title}
          message={`${error.message}${error.requestId ? ` · request_id: ${error.requestId}` : ""}`}
        />
      ) : null}
      <Button disabled={loading || shiftStatus === "online"} onPress={startShift}>
        ცვლის დაწყება
      </Button>
      <Text variant="caption">Tap to start shift</Text>
      <Button disabled={loading || shiftStatus === "offline"} onPress={endShift}>
        ცვლის დასრულება
      </Button>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="სესიის გასუფთავება" onPress={onClearSession} />
    </Screen>
  );
}

function DispatchPanel({
  activeOffer,
  activeRide,
  loading,
  refreshing,
  online,
  onAccept,
  onReject,
  onAdvance,
  onRefresh,
}: {
  activeOffer: ActiveRideOffer | null;
  activeRide: DriverRide | null;
  loading: boolean;
  refreshing: boolean;
  online: boolean;
  onAccept: () => void;
  onReject: () => void;
  onAdvance: () => void;
  onRefresh: () => void;
}) {
  if (activeRide) {
    const nextActionLabel = nextRideActionLabel(activeRide.status);
    return (
      <Card>
        <Text variant="subtitle">აქტიური მგზავრობა</Text>
        <Text>სტატუსი: {rideStatusText(activeRide.status)}</Text>
        <Text variant="caption">
          აყვანა: {activeRide.pickup?.address ?? "-"}
        </Text>
        <Text variant="caption">
          დანიშნულება: {activeRide.dropoff?.address ?? "-"}
        </Text>
        {nextActionLabel ? (
          <Button disabled={loading} onPress={onAdvance}>{nextActionLabel}</Button>
        ) : null}
        <Button disabled={refreshing} onPress={onRefresh}>განახლება</Button>
      </Card>
    );
  }

  if (activeOffer) {
    return (
      <Card>
        <Text variant="subtitle">ახალი შეკვეთა</Text>
        <Text variant="caption">
          აყვანა: {activeOffer.pickup?.address ?? "-"}
        </Text>
        <Text variant="caption">
          დანიშნულება: {activeOffer.dropoff?.address ?? "-"}
        </Text>
        <Text variant="caption">
          ფასი: {activeOffer.fare?.amount ?? "-"} {activeOffer.fare?.currency ?? "GEL"}
        </Text>
        <Text variant="caption">
          იწურება: {activeOffer.expires_at}
        </Text>
        <Button disabled={loading} onPress={onAccept}>მიღება</Button>
        <Button disabled={loading} onPress={onReject}>უარყოფა</Button>
      </Card>
    );
  }

  return (
    <Card>
      <Text variant="subtitle">შეთავაზება</Text>
      <Text>
        {online
          ? "აქტიური შეთავაზება ჯერ არ არის."
          : "შეთავაზებების მისაღებად დაიწყეთ ცვლა."}
      </Text>
      {refreshing ? <LoadingState label="მოწმდება" /> : null}
      <Button disabled={refreshing} onPress={onRefresh}>განახლება</Button>
    </Card>
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
          ბოლო მოთხოვნა: {diagnostics.lastRequestMethod ?? "-"}{" "}
          {diagnostics.lastRequestUrl ?? "-"}
        </Text>
        <Text variant="caption">სტატუსი: {diagnostics.lastStatus ?? "-"}</Text>
        <Text variant="caption">
          token present: {String(diagnostics.tokenPresent ?? false)}
        </Text>
        <Text variant="caption">
          abilities: {diagnostics.tokenAbilities.join(", ") || "-"}
        </Text>
        <Text variant="caption">
          user type: {diagnostics.authUserType ?? "-"}
        </Text>
        <Text variant="caption">
          body: {diagnostics.lastBodyExcerpt ?? "-"}
        </Text>
        <Text variant="caption">
          network: {diagnostics.lastNetworkException ?? "-"}
        </Text>
      </Card>
      <Button onPress={onBack}>დაბრუნება</Button>
    </Screen>
  );
}

function ActionCard({
  title,
  subtitle,
  onPress,
}: {
  title: string;
  subtitle: string;
  onPress: () => void;
}) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress}>
      <Card>
        <Text variant="subtitle">{title}</Text>
        <Text variant="caption">{subtitle}</Text>
      </Card>
    </Pressable>
  );
}

function SecondaryActions({
  onBack,
  onDiagnostics,
}: {
  onBack: () => void;
  onDiagnostics: () => void;
}) {
  return (
    <View style={{ gap: 10 }}>
      <Button onPress={onDiagnostics}>დიაგნოსტიკა</Button>
      <InlineLink label="უკან" onPress={onBack} />
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
      Driver V2 · {APP_VERSION} · {config.APP_ENV}
    </Text>
  );
}

function useDiagnosticsSnapshot(route: DriverScreen) {
  const [snapshot, setSnapshot] = useState(diagnosticsStore.getState());

  useEffect(() => {
    setSnapshot(diagnosticsStore.getState());
    return diagnosticsStore.subscribe(setSnapshot);
  }, [route]);

  return snapshot;
}

function driverErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 401) return "სესიის ვადა ამოიწურა.";
    if (error.status === 403) return "ამ ანგარიშს მძღოლის წვდომა არ აქვს.";
    if (error.status === 404) return "საჭირო რესურსი ჯერ არ არსებობს.";
    if (error.status === 422) return error.message || "მონაცემები არასწორია.";
    if (error.status >= 500) return "სერვერზე დროებითი შეცდომაა.";
    return error.message;
  }

  if (error instanceof Error) return error.message;
  return "ქსელის შეცდომა.";
}

function currentPlatform(): Platform {
  const os = NativePlatform.OS;
  if (os === "ios" || os === "android" || os === "web") return os;
  return "web";
}

async function getCurrentLocation(): Promise<LocationPoint> {
  const Location = await import("expo-location").catch(() => null);

  if (Location) {
    const permission = await Location.requestForegroundPermissionsAsync();
    if (permission.status !== "granted") {
      throw new Error("ლოკაციის ნებართვა საჭიროა ცვლის დასაწყებად.");
    }

    const position = await Location.getCurrentPositionAsync({
      accuracy: Location.Accuracy.High,
    });

    return {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
    };
  }

  return getNavigatorLocation();
}

async function getNavigatorLocation(): Promise<LocationPoint> {
  const navigatorLike = globalThis.navigator as
    | {
        geolocation?: {
          getCurrentPosition: (
            success: (position: { coords: { latitude: number; longitude: number } }) => void,
            error: (error: { message?: string }) => void,
            options?: { enableHighAccuracy?: boolean; timeout?: number; maximumAge?: number },
          ) => void;
        };
      }
    | undefined;

  if (!navigatorLike?.geolocation) {
    throw new Error("ლოკაციის სერვისი მიუწვდომელია ამ გარემოში.");
  }

  return new Promise((resolve, reject) => {
    navigatorLike.geolocation?.getCurrentPosition(
      (position) =>
        resolve({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        }),
      (locationError) =>
        reject(
          new Error(
            locationError.message ||
              "ლოკაციის ნებართვა საჭიროა ცვლის დასაწყებად.",
          ),
        ),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 },
    );
  });
}

async function clearStoredSessionSafely() {
  try {
    await auth.clearSession();
  } catch (error) {
    diagnosticsStore.recordError({
      code: "driver.session.clear_failed",
      message: error instanceof Error ? error.message : "Session clear failed.",
      bodyExcerpt: stackExcerpt(error),
    });
  }
}

function readPublicEnv(key: string): string | undefined {
  const env = globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
  };

  return env.process?.env?.[key];
}

function stackExcerpt(error: unknown, componentStack?: string): string {
  const stack = error instanceof Error ? error.stack : undefined;
  return [stack, componentStack]
    .filter(Boolean)
    .join("\n")
    .slice(0, 900);
}

const inputStyle = {
  backgroundColor: "#ffffff",
  borderColor: "#d7dce5",
  borderRadius: 8,
  borderWidth: 1,
  color: "#151922",
  fontSize: 18,
  minHeight: 52,
  paddingHorizontal: 14,
  paddingVertical: 12,
};
