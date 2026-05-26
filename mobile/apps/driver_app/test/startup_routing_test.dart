import 'package:core/core.dart';
import 'package:driver_app/di/locator.dart';
import 'package:driver_app/features/application/presentation/driver_application_page.dart';
import 'package:driver_app/features/auth/application/driver_auth_flow.dart';
import 'package:driver_app/features/auth/presentation/phone_page.dart';
import 'package:driver_app/features/auth/presentation/welcome_page.dart';
import 'package:driver_app/features/diagnostics/application/route_diagnostics_marker.dart';
import 'package:driver_app/features/onboarding/presentation/driver_onboarding_status_page.dart';
import 'package:driver_app/features/splash/presentation/splash_page.dart';
import 'package:driver_app/features/splash/presentation/startup_error_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:network/network.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

void main() {
  testWidgets('no token fresh launch shows welcome', (tester) async {
    await tester.pumpWidget(_testApp(
      overrides: [tokenStoreProvider.overrideWithValue(_FakeTokenStore())],
    ));

    await tester.pumpAndSettle();

    expect(find.text('შესვლა'), findsOneWidget);
    expect(find.text('მძღოლად რეგისტრაცია'), findsOneWidget);
    expect(find.text('დიაგნოსტიკა'), findsOneWidget);
    expect(find.textContaining('სერვერთან კავშირი ვერ'), findsNothing);
  });

  testWidgets('startup server error screen shows diagnostics details',
      (tester) async {
    final diagnostics = NetworkDiagnosticsRecorder()
      ..value = const NetworkDiagnosticsState(
        lastRequestUrl: 'https://ride.365sakartvelo.com/api/v1/auth/me',
        lastResponseStatus: 503,
        lastResponseBodyExcerpt: '{"message":"temporary"}',
        lastNetworkException: 'HTTP 503 / badResponse',
        currentRoute: '/startup-error',
        tokenPresent: true,
      );

    await tester.pumpWidget(_testApp(
      initialLocation: '/startup-error',
      overrides: [
        tokenStoreProvider
            .overrideWithValue(_FakeTokenStore(token: 'redacted')),
        networkDiagnosticsProvider.overrideWithValue(diagnostics),
      ],
    ));

    await tester.pumpAndSettle();

    expect(find.text('სერვერის დროებითი შეცდომა'), findsOneWidget);
    expect(find.text('დიაგნოსტიკა'), findsOneWidget);
    expect(
      find.text('https://ride.365sakartvelo.com/api/v1/auth/me'),
      findsOneWidget,
    );
    expect(find.text('503'), findsOneWidget);
    expect(find.text('{"message":"temporary"}'), findsOneWidget);
    expect(find.text('yes'), findsOneWidget);
  });

  testWidgets(
      'existing onboarding token shows status screen instead of application',
      (tester) async {
    await tester.pumpWidget(_testApp(
      overrides: [
        tokenStoreProvider.overrideWithValue(_FakeTokenStore(
          token: 'onboarding-token',
          abilities: const ['driver:onboarding'],
          userType: 'driver',
        )),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(),
        ),
      ],
    ));

    await tester.pumpAndSettle();

    expect(find.text('მძღოლის განაცხადი'), findsOneWidget);
    expect(find.text('განაცხადი ჯერ არ არის შევსებული'), findsOneWidget);
    expect(find.text('განაცხადის შევსება'), findsOneWidget);
    expect(find.text('შესვლა სხვა ნომრით'), findsOneWidget);
    expect(find.text('სესიის გასუფთავება'), findsOneWidget);
    expect(find.text('Application route'), findsNothing);
    expect(find.textContaining('სერვერთან კავშირი ვერ'), findsNothing);
  });

  testWidgets('tapping continue opens application', (tester) async {
    await tester.pumpWidget(_testApp(
      initialLocation: '/onboarding',
      overrides: [
        tokenStoreProvider.overrideWithValue(_FakeTokenStore(
          token: 'onboarding-token',
          abilities: const ['driver:onboarding'],
          userType: 'driver',
        )),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(),
        ),
      ],
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('განაცხადის შევსება'));
    await tester.pumpAndSettle();

    expect(find.text('პირადი ინფორმაცია'), findsOneWidget);
    expect(find.text('მთავარ არჩევანზე დაბრუნება'), findsOneWidget);
    expect(find.text('სესიის გასუფთავება'), findsOneWidget);
  });

  testWidgets('login another number clears session and opens login flow',
      (tester) async {
    final store = _FakeTokenStore(
      token: 'onboarding-token',
      abilities: const ['driver:onboarding'],
      userType: 'driver',
    );

    await tester.pumpWidget(_testApp(
      initialLocation: '/onboarding',
      overrides: [
        tokenStoreProvider.overrideWithValue(store),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(),
        ),
      ],
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('შესვლა სხვა ნომრით'));
    await tester.pumpAndSettle();

    expect(store.token, isNull);
    expect(find.text('თუ უკვე დარეგისტრირებული ხართ მძღოლად'), findsOneWidget);
  });

  testWidgets('diagnostics is accessible before login', (tester) async {
    await tester.pumpWidget(_testApp(initialLocation: '/welcome'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('დიაგნოსტიკა'));
    await tester.pumpAndSettle();

    expect(find.text('Diagnostics route'), findsOneWidget);
  });

  testWidgets('diagnostics is accessible from onboarding status',
      (tester) async {
    await tester.pumpWidget(_testApp(
      initialLocation: '/onboarding',
      overrides: [
        tokenStoreProvider.overrideWithValue(
          _FakeTokenStore(token: 'onboarding-token'),
        ),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(),
        ),
      ],
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('დიაგნოსტიკა'));
    await tester.pumpAndSettle();

    expect(find.text('Diagnostics route'), findsOneWidget);
  });

  testWidgets('approved driver still routes to dashboard', (tester) async {
    await tester.pumpWidget(_testApp(
      overrides: [
        tokenStoreProvider.overrideWithValue(
          _FakeTokenStore(token: 'driver-token'),
        ),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(driverMe: _approvedDriverMe),
        ),
      ],
    ));

    await tester.pumpAndSettle();

    expect(find.text('Dashboard route'), findsOneWidget);
  });

  testWidgets('invalid token can be cleared and returns to welcome',
      (tester) async {
    final store = _FakeTokenStore(token: 'bad-token');

    await tester.pumpWidget(_testApp(
      overrides: [
        tokenStoreProvider.overrideWithValue(store),
        driverProfileRepositoryProvider.overrideWithValue(
          _FakeDriverProfileRepository(
            error: ApiError(
              code: 'auth.invalid_token',
              message: 'Unauthenticated.',
              httpStatus: 401,
            ),
          ),
        ),
      ],
    ));

    await tester.pumpAndSettle();

    expect(find.text('სესიის ვადა ამოიწურა'), findsOneWidget);
    expect(find.text('სესიის გასუფთავება'), findsOneWidget);

    await tester.tap(find.text('სესიის გასუფთავება'));
    await tester.pumpAndSettle();

    expect(store.token, isNull);
    expect(find.text('შესვლა'), findsOneWidget);
    expect(find.text('მძღოლად რეგისტრაცია'), findsOneWidget);
  });

  testWidgets('welcome buttons route to correct phone modes', (tester) async {
    await tester.pumpWidget(_testApp(initialLocation: '/welcome'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('შესვლა'));
    await tester.pumpAndSettle();
    expect(
      find.text('თუ უკვე დარეგისტრირებული ხართ მძღოლად'),
      findsOneWidget,
    );

    await tester.tap(find.text('მძღოლად რეგისტრაცია'));
    await tester.pumpAndSettle();
    expect(
      find.text('შეავსეთ განაცხადი და დაელოდეთ დადასტურებას'),
      findsOneWidget,
    );
  });
}

Widget _testApp({
  String initialLocation = '/',
  List<Override> overrides = const [],
}) {
  final router = GoRouter(
    initialLocation: initialLocation,
    routes: [
      GoRoute(
        path: '/',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/',
          child: SplashPage(),
        ),
      ),
      GoRoute(
        path: '/welcome',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/welcome',
          child: WelcomePage(),
        ),
      ),
      GoRoute(
        path: '/startup-error',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/startup-error',
          child: StartupErrorPage(),
        ),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/onboarding',
          child: DriverOnboardingStatusPage(),
        ),
      ),
      GoRoute(
        path: '/application',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/application',
          child: DriverApplicationPage(),
        ),
      ),
      GoRoute(
        path: '/home',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/home',
          child: Scaffold(body: Center(child: Text('Dashboard route'))),
        ),
      ),
      GoRoute(
        path: '/diagnostics',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/diagnostics',
          child: Scaffold(body: Center(child: Text('Diagnostics route'))),
        ),
      ),
      GoRoute(
        path: '/auth/phone',
        builder: (_, state) {
          final flow =
              driverAuthFlowFromQuery(state.uri.queryParameters['mode']);
          return RouteDiagnosticsMarker(
            route: '/auth/phone?mode=${flow.queryValue}',
            child: PhonePage(flow: flow),
          );
        },
      ),
    ],
  );

  return ProviderScope(
    overrides: [
      envProvider.overrideWithValue(_env),
      ...overrides,
    ],
    child: MaterialApp.router(
      theme: AppTheme.light(),
      routerConfig: router,
    ),
  );
}

const _env = EnvConfig(
  flavor: AppFlavor.prod,
  apiBaseUrl: 'https://ride.365sakartvelo.com',
  wsUrl: 'wss://ride.365sakartvelo.com',
  wsKey: '',
  sentryDsn: '',
  googleMapsKey: '',
);

class _FakeTokenStore extends TokenStore {
  _FakeTokenStore({
    this.token,
    this.abilities = const [],
    this.userType,
  }) : super(namespace: 'driver_startup_test');

  String? token;
  String? deviceUuid;
  List<String> abilities;
  String? userType;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(
      {required String token, required DateTime expiresAt}) async {
    this.token = token;
  }

  @override
  Future<void> clear() async {
    token = null;
    abilities = const [];
    userType = null;
  }

  @override
  Future<List<String>> readAuthAbilities() async => abilities;

  @override
  Future<String?> readAuthUserType() async => userType;

  @override
  Future<void> writeAuthContext({
    required List<String> abilities,
    required String? userType,
  }) async {
    this.abilities = abilities;
    this.userType = userType;
  }

  @override
  Future<String?> readDeviceUuid() async => deviceUuid;

  @override
  Future<void> writeDeviceUuid(String uuid) async {
    deviceUuid = uuid;
  }
}

class _FakeDriverProfileRepository extends DriverProfileRepository {
  _FakeDriverProfileRepository({
    this.error,
    this.driverMe = _onboardingDriverMe,
  }) : super(client: _unusedClient());

  final Object? error;
  final DriverMe driverMe;

  @override
  Future<DriverMe> me() async {
    final error = this.error;
    if (error != null) throw error;
    return driverMe;
  }

  @override
  Future<DriverApplication?> application() async {
    return null;
  }
}

const _onboardingDriverMe = DriverMe(
  userId: 'driver-1',
  userType: 'driver',
  phone: '+995555123456',
  context: DriverContext(
    hasDriverProfile: false,
    canGoOnline: false,
    needsApplication: true,
    canSubmitApplication: true,
    state: DriverRuntimeState.noDriverProfile,
    reasonIfCannotGoOnline: 'driver.no_profile',
  ),
);

const _approvedDriverMe = DriverMe(
  userId: 'driver-1',
  userType: 'driver',
  phone: '+995555123456',
  context: DriverContext(
    hasDriverProfile: true,
    canGoOnline: true,
    driverProfileStatus: 'approved',
    state: DriverRuntimeState.offline,
  ),
);

ApiClient _unusedClient() {
  return ApiClient(
    env: _env,
    tokenStore: TokenStore(namespace: 'driver_startup_unused'),
    appPlatform: 'test',
    appVersion: '0.1.0',
    deviceUuidProvider: () async => '550e8400-e29b-41d4-a716-446655440000',
  );
}
