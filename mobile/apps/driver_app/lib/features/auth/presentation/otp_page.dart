import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:network/network.dart';
import 'package:ui_kit/ui_kit.dart';
import 'package:uuid/uuid.dart';

import '../../../di/locator.dart';

class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({super.key, required this.phone});

  final String phone;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  final _controller = TextEditingController();
  bool _busy = false;
  String? _error;

  Future<void> _verify() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final TokenStore store = ref.read(tokenStoreProvider);
      var deviceUuid = await store.readDeviceUuid();
      if (deviceUuid == null) {
        deviceUuid = const Uuid().v4();
        await store.writeDeviceUuid(deviceUuid);
      }

      await ref.read(authRepositoryProvider).verifyOtp(
            phone: widget.phone,
            code: _controller.text.trim(),
            purpose: 'driver_signup',
            deviceUuid: deviceUuid,
            platform: Theme.of(context).platform == TargetPlatform.iOS ? 'ios' : 'android',
            appVersion: '0.1.0',
          );
      if (!mounted) return;
      context.go('/home');
    } on ApiError catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Verify code')),
      body: Padding(
        padding: const EdgeInsets.all(Insets.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: Insets.xxl),
            Text('Code sent to ${widget.phone}', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: Insets.l),
            AppTextField(
              controller: _controller,
              label: '6-digit code',
              keyboardType: TextInputType.number,
              autofocus: true,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            ),
            if (_error != null) ...[
              const SizedBox(height: Insets.s),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const Spacer(),
            PrimaryButton(label: 'Verify', onPressed: _verify, busy: _busy),
          ],
        ),
      ),
    );
  }
}
