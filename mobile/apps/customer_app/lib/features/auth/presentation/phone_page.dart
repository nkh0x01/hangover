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
      appBar: AppBar(title: const Text('Sign in')),
      body: Padding(
        padding: const EdgeInsets.all(Insets.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: Insets.xxl),
            Text('ტელეფონის ნომერი', style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: Insets.l),
            AppTextField(controller: _controller, label: 'Phone', keyboardType: TextInputType.phone),
            if (_error != null) ...[
              const SizedBox(height: Insets.s),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const Spacer(),
            PrimaryButton(label: 'Send code', onPressed: _send, busy: _busy),
          ],
        ),
      ),
    );
  }
}
