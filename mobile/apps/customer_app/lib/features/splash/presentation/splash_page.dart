import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:network/network.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';

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
    // Give the splash animation 600 ms before routing — premium feel
    // and prevents a perceived flicker on warm starts.
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (!mounted) return;
    final TokenStore store = ref.read(tokenStoreProvider);
    final token = await store.read();
    if (!mounted) return;
    if (token == null) {
      context.go('/auth/phone');
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: SplashContent());
  }
}
