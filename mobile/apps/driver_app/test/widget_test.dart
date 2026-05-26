import 'package:core/core.dart';
import 'package:driver_app/features/home/presentation/home_page.dart';
import 'package:driver_app/features/profile/state/driver_profile_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

void main() {
  test('AppTheme builds without error', () {
    expect(AppTheme.light(), isNotNull);
    expect(AppTheme.dark(), isNotNull);
  });

  testWidgets('missing driver profile shows registration state only',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          driverMeProvider.overrideWith((ref) async {
            return const DriverMe(
              userId: 'user-1',
              userType: 'driver',
              phone: '+995555123456',
              context: DriverContext(
                hasDriverProfile: false,
                canGoOnline: false,
                state: DriverRuntimeState.noDriverProfile,
                reasonIfCannotGoOnline: 'driver.no_profile',
                todayEarnings: null,
                onlineStatus: null,
              ),
            );
          }),
        ],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const HomePage(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('მძღოლის პროფილი არ არის ნაპოვნი'), findsOneWidget);
    expect(find.text('მძღოლის განაცხადის შევსება'), findsOneWidget);
    expect(find.textContaining('Tap to start your shift'), findsNothing);
    expect(find.textContaining('GEL'), findsNothing);
  });

  testWidgets('401 profile load shows auth error', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          driverMeProvider.overrideWith((ref) {
            throw ApiError(
              code: 'auth.invalid_token',
              message: 'Unauthenticated.',
              httpStatus: 401,
            );
          }),
        ],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const HomePage(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('ავტორიზაცია ვერ დადასტურდა'), findsOneWidget);
    expect(find.text('ხელახლა შესვლა'), findsOneWidget);
  });

  testWidgets('403 profile load shows role/token context error',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          driverMeProvider.overrideWith((ref) {
            throw ApiError(
              code: 'auth.forbidden',
              message: 'Invalid ability provided.',
              httpStatus: 403,
            );
          }),
        ],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const HomePage(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('არასწორი ავტორიზაციის როლი'), findsOneWidget);
    expect(find.textContaining('driver:onboarding'), findsOneWidget);
  });

  testWidgets('404 profile load shows missing endpoint diagnostic',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          driverMeProvider.overrideWith((ref) {
            throw ApiError(
              code: 'http.not_found',
              message: 'Not found.',
              httpStatus: 404,
            );
          }),
        ],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const HomePage(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('სერვერის endpoint ვერ მოიძებნა'), findsOneWidget);
    expect(find.textContaining('Diagnostics'), findsWidgets);
  });
}
