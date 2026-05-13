import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../demo/state/demo_mode_controller.dart';

class PhonePage extends ConsumerStatefulWidget {
  const PhonePage({super.key});

  @override
  ConsumerState<PhonePage> createState() => _PhonePageState();
}

class _PhonePageState extends ConsumerState<PhonePage> {
  final _controller = TextEditingController(text: '+995');
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .requestOtp(phone: _controller.text.trim(), purpose: 'signup');
      if (!mounted) return;
      context.go('/auth/otp?phone=${Uri.encodeComponent(_controller.text.trim())}');
    } on ApiError catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.surface),
        child: SafeArea(
          child: Column(
            children: [
              // Gradient brand hero band — premium-app feel without a full splash.
              Container(
                margin: const EdgeInsets.fromLTRB(Insets.l, Insets.l, Insets.l, Insets.xl),
                padding: const EdgeInsets.all(Insets.xl),
                decoration: BoxDecoration(
                  gradient: AppGradients.primary,
                  borderRadius: BorderRadius.circular(Radii.xl),
                  boxShadow: AppShadows.heroGreen,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const BrandLogo(size: BrandLogoSize.s, showWordmark: false),
                    const SizedBox(height: Insets.l),
                    Text(
                      'მოგესალმებით',
                      style: AppType.headlineL.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: Insets.xs),
                    Text(
                      'Tbilisi-ში სკუტერი ერთი დაკაკუნებით.',
                      style: AppType.body.copyWith(color: Colors.white.withValues(alpha: 0.85)),
                    ),
                    Text(
                      'Tbilisi on two wheels.',
                      style: AppType.body.copyWith(color: Colors.white.withValues(alpha: 0.65)),
                    ),
                  ],
                ),
              ),

              // Form
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: Insets.xl),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('ტელეფონის ნომერი · Phone', style: AppType.label),
                    const SizedBox(height: Insets.s),
                    AppTextField(
                      controller: _controller,
                      keyboardType: TextInputType.phone,
                      prefixIcon: const Padding(
                        padding: EdgeInsets.only(left: Insets.m, right: Insets.s),
                        child: Icon(Icons.phone_iphone_rounded, color: AppColors.inkSoft, size: 20),
                      ),
                      helper: 'ბმულს გამოგიგზავნით 6-ციფრიან კოდს · We text you a 6-digit code',
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: Insets.s),
                      Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                    ],
                  ],
                ),
              ),

              const Spacer(),

              // Hero gradient CTA
              Padding(
                padding: const EdgeInsets.fromLTRB(Insets.xl, 0, Insets.xl, Insets.l),
                child: GradientButton(
                  label: 'კოდის გაგზავნა · Send code',
                  onPressed: _send,
                  busy: _busy,
                  trailing: const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 18),
                ),
              ),

              // Preview entry. Available in dev AND staging so QA can
              // demo from an installed staging APK without backend
              // round-trips. Hidden in prod by the env.isProd gate.
              if (!ref.watch(envProvider).isProd)
                Padding(
                  padding: const EdgeInsets.fromLTRB(Insets.xl, 0, Insets.xl, Insets.s),
                  child: OutlinedButton.icon(
                    onPressed: () {
                      ref.read(demoModeProvider.notifier).activate();
                      context.go('/home');
                    },
                    icon: const Icon(Icons.visibility_rounded, size: 18),
                    label: const Text('Preview app (no backend)'),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(48),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(Radii.l),
                      ),
                    ),
                  ),
                ),

              Padding(
                padding: const EdgeInsets.only(bottom: Insets.l),
                child: Text(
                  'By continuing you agree to our Terms and Privacy Policy',
                  textAlign: TextAlign.center,
                  style: AppType.label,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
