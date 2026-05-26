import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/application/presentation/driver_application_page.dart';
import '../features/auth/application/driver_auth_flow.dart';
import '../features/auth/presentation/otp_page.dart';
import '../features/auth/presentation/phone_page.dart';
import '../features/auth/presentation/welcome_page.dart';
import '../features/diagnostics/application/route_diagnostics_marker.dart';
import '../features/diagnostics/presentation/diagnostics_page.dart';
import '../features/home/presentation/home_page.dart';
import '../features/splash/presentation/splash_page.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/',
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
      GoRoute(
        path: '/auth/otp',
        builder: (_, state) {
          final flow =
              driverAuthFlowFromQuery(state.uri.queryParameters['mode']);
          return RouteDiagnosticsMarker(
            route: '/auth/otp?mode=${flow.queryValue}',
            child: OtpPage(
              phone: state.uri.queryParameters['phone'] ?? '',
              flow: flow,
            ),
          );
        },
      ),
      GoRoute(
        path: '/home',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/home',
          child: HomePage(),
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
        path: '/diagnostics',
        builder: (_, __) => const RouteDiagnosticsMarker(
          route: '/diagnostics',
          child: DiagnosticsPage(),
        ),
      ),
    ],
  );
});
