import 'package:core/core.dart';
import 'package:driver_app/di/locator.dart';
import 'package:driver_app/features/application/presentation/driver_application_page.dart';
import 'package:driver_app/features/auth/presentation/welcome_page.dart';
import 'package:driver_app/features/home/presentation/home_page.dart';
import 'package:driver_app/features/profile/state/driver_profile_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:network/network.dart';
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
    expect(find.textContaining('სერვერთან კავშირი ვერ'), findsNothing);
    expect(find.textContaining('Tap to start your shift'), findsNothing);
    expect(find.textContaining('GEL'), findsNothing);
  });

  testWidgets('welcome screen has login and driver registration choices',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: const WelcomePage(),
      ),
    );

    expect(find.text('შესვლა'), findsOneWidget);
    expect(find.text('მძღოლად რეგისტრაცია'), findsOneWidget);
    expect(find.text('დიაგნოსტიკა'), findsOneWidget);
    expect(
      find.text('თუ უკვე დარეგისტრირებული ხართ მძღოლად'),
      findsOneWidget,
    );
    expect(
      find.text('შეავსეთ განაცხადი და დაელოდეთ დადასტურებას'),
      findsOneWidget,
    );
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

    expect(find.text('სესიის ვადა ამოიწურა'), findsOneWidget);
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

    expect(find.text('წვდომა შეზღუდულია'), findsOneWidget);
    expect(find.textContaining('შესაბამისი წვდომა'), findsOneWidget);
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

  testWidgets('404 application draft load keeps create form open',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          driverProfileRepositoryProvider.overrideWithValue(
            _FakeDriverProfileRepository(
              applicationError: ApiError(
                code: 'http.not_found',
                message: 'Not found.',
                httpStatus: 404,
              ),
            ),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const DriverApplicationPage(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('მძღოლის განაცხადი'), findsOneWidget);
    expect(find.text('პირადი ინფორმაცია'), findsOneWidget);
    expect(find.textContaining('სერვერთან კავშირი ვერ'), findsNothing);
  });
}

class _FakeDriverProfileRepository extends DriverProfileRepository {
  _FakeDriverProfileRepository({this.applicationError})
      : super(client: _unusedClient());

  final Object? applicationError;

  @override
  Future<DriverApplication?> application() async {
    final error = applicationError;
    if (error != null) throw error;
    return null;
  }
}

ApiClient _unusedClient() {
  return ApiClient(
    env: const EnvConfig(
      flavor: AppFlavor.prod,
      apiBaseUrl: 'https://ride.365sakartvelo.com',
      wsUrl: '',
      wsKey: '',
      sentryDsn: '',
      googleMapsKey: '',
    ),
    tokenStore: TokenStore(namespace: 'driver_test'),
    appPlatform: 'test',
    appVersion: '0.1.0',
    deviceUuidProvider: () async => '550e8400-e29b-41d4-a716-446655440000',
  );
}
