import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:network/network.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../auth/application/driver_post_login_router.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';

class SplashPage extends ConsumerStatefulWidget {
  const SplashPage({super.key});

  @override
  ConsumerState<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends ConsumerState<SplashPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _decide());
  }

  Future<void> _decide() async {
    final TokenStore store = ref.read(tokenStoreProvider);
    final token = await store.read();
    if (!mounted) return;
    if (token == null) {
      context.go('/welcome');
    } else {
      try {
        final me = await ref.read(driverProfileRepositoryProvider).me();
        if (!mounted) return;
        context.go(routeForDriverContext(me.context));
      } on ApiError catch (error) {
        if (!mounted) return;
        final diagnostics = ref.read(networkDiagnosticsProvider);
        diagnostics.value = diagnostics.value.copyWith(
          lastResponseStatus: error.httpStatus,
          lastResponseBodyExcerpt: '${error.code}: ${error.message}',
          lastNetworkException: 'ApiError: ${error.code}',
          tokenPresent: true,
        );
        context.go('/startup-error');
      } catch (_) {
        if (mounted) context.go('/startup-error');
      }
    }
  }

  @override
  Widget build(BuildContext context) => const Scaffold(
        body: SafeArea(
          child: Padding(
            padding: EdgeInsets.all(Insets.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DriverBuildIdentityLabel(),
                Expanded(child: LoadingState()),
              ],
            ),
          ),
        ),
      );
}
