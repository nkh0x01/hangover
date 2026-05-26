import 'package:core/core.dart';
import 'package:rides/rides.dart';

bool isDriverLoginAbility(List<String> abilities) {
  return abilities.contains('driver') ||
      abilities.contains('driver:onboarding');
}

String routeForDriverContext(DriverContext context) {
  return switch (context.state) {
    DriverRuntimeState.noDriverProfile ||
    DriverRuntimeState.applicationDraft ||
    DriverRuntimeState.applicationRejected =>
      '/application',
    _ => '/home',
  };
}

String driverLoginErrorMessage(Object error) {
  final apiError = apiErrorFrom(error);
  if (apiError == null) {
    final text = error.toString().toLowerCase();
    if (text.contains('timeout') || text.contains('timed out')) {
      return 'ქსელის დრო ამოიწურა. გადაამოწმეთ ინტერნეტი და სცადეთ თავიდან.';
    }
    return 'სერვერთან კავშირი ვერ მოხერხდა. გახსენით Diagnostics დეტალებისთვის.';
  }

  return switch (apiError.httpStatus) {
    401 => 'ავტორიზაცია ვერ დადასტურდა. გთხოვთ, შეხვიდეთ თავიდან.',
    403 =>
      'არასწორი ავტორიზაციის როლია. Driver აპისთვის საჭიროა driver/onboarding token.',
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
