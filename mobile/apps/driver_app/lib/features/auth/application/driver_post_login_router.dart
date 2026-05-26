import 'package:core/core.dart';
import 'package:rides/rides.dart';

import 'driver_auth_flow.dart';

bool isDriverLoginAbility(List<String> abilities) {
  return abilities.contains('driver') ||
      abilities.contains('driver:onboarding');
}

String routeForDriverContext(DriverContext context) {
  return context.canShowDashboard ? '/home' : '/onboarding';
}

String routeForDriverContextOnStartup(DriverContext context) {
  return context.canShowDashboard ? '/home' : '/onboarding';
}

String routeForDriverContextAfterRegistrationChoice(DriverContext context) {
  if (context.canShowDashboard) return '/home';
  return switch (context.state) {
    DriverRuntimeState.noDriverProfile ||
    DriverRuntimeState.applicationDraft ||
    DriverRuntimeState.applicationRejected =>
      '/application',
    _ when context.needsApplication || context.canSubmitApplication =>
      '/application',
    _ => '/onboarding',
  };
}

String routeForDriverContextAfterOtp(
  DriverContext context,
  DriverAuthFlow flow,
) {
  if (flow == DriverAuthFlow.registration) {
    return routeForDriverContextAfterRegistrationChoice(context);
  }

  return routeForDriverContext(context);
}

String driverLoginErrorMessage(Object error) {
  final apiError = apiErrorFrom(error);
  if (apiError == null) {
    final text = error.toString().toLowerCase();
    if (text.contains('timeout') || text.contains('timed out')) {
      return 'ქსელის დრო ამოიწურა. გადაამოწმეთ ინტერნეტი და სცადეთ თავიდან.';
    }
    return 'შესვლა ვერ დასრულდა. დეტალები Diagnostics-შია.';
  }

  return switch (apiError.httpStatus) {
    401 => 'სესიის ვადა ამოიწურა, გთხოვთ თავიდან შეხვიდეთ.',
    403 => apiError.code == 'auth.wrong_app_context'
        ? 'ეს ნომერი მძღოლის ანგარიშად არ არის რეგისტრირებული. გსურთ მძღოლად რეგისტრაცია?'
        : 'ამ მოქმედებისთვის არ გაქვთ შესაბამისი წვდომა.',
    404 =>
      'სერვერზე საჭირო endpoint ვერ მოიძებნა. გახსენით Diagnostics და გადაამოწმეთ URL.',
    500 ||
    502 ||
    503 =>
      'სერვერზე დროებითი შეცდომაა. სცადეთ მოგვიანებით ან გახსენით Diagnostics.',
    _ => apiError.message,
  };
}

ApiError? apiErrorFrom(Object error) {
  if (error is ApiError) return error;
  try {
    final candidate = (error as dynamic).error;
    if (candidate is ApiError) return candidate;
  } catch (_) {}
  return null;
}
