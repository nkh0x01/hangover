import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/presentation/otp_page.dart';
import '../features/auth/presentation/phone_page.dart';
import '../features/home/presentation/home_page.dart';
import '../features/ride/presentation/destination_page.dart';
import '../features/ride/presentation/fare_estimate_page.dart';
import '../features/ride/presentation/ride_tracking_page.dart';
import '../features/splash/presentation/splash_page.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(path: '/', builder: (_, __) => const SplashPage()),
      GoRoute(path: '/auth/phone', builder: (_, __) => const PhonePage()),
      GoRoute(
        path: '/auth/otp',
        builder: (_, state) => OtpPage(phone: state.uri.queryParameters['phone'] ?? ''),
      ),
      GoRoute(path: '/home', builder: (_, __) => const HomePage()),
      GoRoute(path: '/ride/destination', builder: (_, __) => const DestinationPage()),
      GoRoute(path: '/ride/estimate', builder: (_, __) => const FareEstimatePage()),
      GoRoute(
        path: '/ride/:id',
        builder: (_, state) => RideTrackingPage(rideId: state.pathParameters['id']!),
      ),
    ],
    errorBuilder: (_, __) => const Scaffold(
      body: Center(child: Text('Route not found')),
    ),
  );
});
