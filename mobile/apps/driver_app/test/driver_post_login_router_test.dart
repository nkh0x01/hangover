import 'package:core/core.dart';
import 'package:driver_app/features/auth/application/driver_post_login_router.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rides/rides.dart';

void main() {
  test('driver onboarding ability is accepted for Driver app login', () {
    expect(isDriverLoginAbility(const ['driver:onboarding']), isTrue);
    expect(isDriverLoginAbility(const ['driver']), isTrue);
    expect(isDriverLoginAbility(const ['customer']), isFalse);
  });

  test('driver onboarding context routes to application form', () {
    const context = DriverContext(
      hasDriverProfile: false,
      canGoOnline: false,
      state: DriverRuntimeState.noDriverProfile,
      reasonIfCannotGoOnline: 'driver.no_profile',
    );

    expect(routeForDriverContext(context), '/application');
  });

  test('pending review context routes to home state screen', () {
    const context = DriverContext(
      hasDriverProfile: false,
      canGoOnline: false,
      state: DriverRuntimeState.applicationPending,
      applicationStatus: 'pending',
      reasonIfCannotGoOnline: 'application.pending_review',
    );

    expect(routeForDriverContext(context), '/home');
  });

  test('approved driver context routes to home dashboard', () {
    const context = DriverContext(
      hasDriverProfile: true,
      canGoOnline: true,
      state: DriverRuntimeState.offline,
      driverProfileStatus: 'approved',
    );

    expect(routeForDriverContext(context), '/home');
  });

  test('401 maps to auth error instead of generic connection failure', () {
    final message = driverLoginErrorMessage(
      ApiError(
        code: 'auth.invalid_token',
        message: 'Unauthenticated.',
        httpStatus: 401,
      ),
    );

    expect(message, contains('ავტორიზაცია'));
    expect(message, isNot(contains('სერვერთან კავშირი ვერ მოხერხდა')));
  });

  test('403 maps to role/token context error', () {
    final message = driverLoginErrorMessage(
      ApiError(
        code: 'auth.forbidden',
        message: 'Invalid ability provided.',
        httpStatus: 403,
      ),
    );

    expect(message, contains('Driver აპისთვის საჭიროა'));
  });

  test('404 maps to missing endpoint diagnostic', () {
    final message = driverLoginErrorMessage(
      ApiError(
        code: 'http.not_found',
        message: 'Not found.',
        httpStatus: 404,
      ),
    );

    expect(message, contains('endpoint'));
  });

  test('timeout maps to network diagnostic', () {
    final message = driverLoginErrorMessage('connection timed out');

    expect(message, contains('დრო ამოიწურა'));
  });
}
