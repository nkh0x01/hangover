import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';

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
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Insets.l),
              const BrandLogo(size: BrandLogoSize.m),
              const SizedBox(height: Insets.xxl),
              Text('მოგესალმებით', style: Theme.of(context).textTheme.headlineLarge),
              const SizedBox(height: Insets.xs),
              Text(
                'Tbilisi-ში სკუტერი ერთი დაკაკუნებით.\nQuick scooter rides across Tbilisi.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: Insets.xxl),
              Text(
                'ტელეფონის ნომერი',
                style: Theme.of(context).textTheme.labelMedium,
              ),
              const SizedBox(height: Insets.s),
              AppTextField(
                controller: _controller,
                keyboardType: TextInputType.phone,
                helper: 'ბმულს გამოგიგზავნით 6-ციფრიან კოდს',
              ),
              if (_error != null) ...[
                const SizedBox(height: Insets.s),
                Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ],
              const Spacer(),
              PrimaryButton(label: 'კოდის გაგზავნა / Send code', onPressed: _send, busy: _busy),
              const SizedBox(height: Insets.m),
              Center(
                child: Text(
                  'By continuing you agree to our Terms and Privacy Policy',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
