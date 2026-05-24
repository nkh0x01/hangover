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
}
