import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/application/presentation/driver_application_page.dart';
import '../features/auth/presentation/otp_page.dart';
import '../features/auth/presentation/phone_page.dart';
import '../features/diagnostics/presentation/diagnostics_page.dart';
import '../features/home/presentation/home_page.dart';
import '../features/splash/presentation/splash_page.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(path: '/', builder: (_, __) => const SplashPage()),
      GoRoute(path: '/auth/phone', builder: (_, __) => const PhonePage()),
      GoRoute(
        path: '/auth/otp',
        builder: (_, state) =>
            OtpPage(phone: state.uri.queryParameters['phone'] ?? ''),
      ),
      GoRoute(path: '/home', builder: (_, __) => const HomePage()),
      GoRoute(
        path: '/application',
        builder: (_, __) => const DriverApplicationPage(),
      ),
      GoRoute(
          path: '/diagnostics', builder: (_, __) => const DiagnosticsPage()),
    ],
  );
});
