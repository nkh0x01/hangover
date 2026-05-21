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

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

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
            purpose: 'signup',
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
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(leading: const BackButton(), title: const Text('Verify code')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Insets.l),
              Text('კოდი გამოგზავნილია', style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: Insets.xs),
              Text(
                widget.phone,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(color: AppColors.inkSoft),
              ),
              const SizedBox(height: Insets.xl),
              _OtpDigits(controller: _controller),
              if (_error != null) ...[
                const SizedBox(height: Insets.m),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                  textAlign: TextAlign.center,
                ),
              ],
              const SizedBox(height: Insets.l),
              Center(
                child: Text(
                  'კოდი არ მოგივიდა? ხელახლა გაგზავნა 0:47-ში',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
              const Spacer(),
              PrimaryButton(label: 'დადასტურება / Verify', onPressed: _verify, busy: _busy),
            ],
          ),
        ),
      ),
    );
  }
}

/// Visual six-digit display that mirrors the underlying controller.
class _OtpDigits extends StatelessWidget {
  const _OtpDigits({required this.controller});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        AnimatedBuilder(
          animation: controller,
          builder: (_, __) {
            final value = controller.text.padRight(6, ' ');
            return Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: List.generate(6, (i) {
                final filled = value[i] != ' ';
                final active = i == controller.text.length;
                return _DigitCell(char: value[i], filled: filled, active: active);
              }),
            );
          },
        ),
        Positioned.fill(
          child: TextField(
            controller: controller,
            keyboardType: TextInputType.number,
            autofocus: true,
            maxLength: 6,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              counterText: '',
              border: InputBorder.none,
              filled: false,
            ),
            style: const TextStyle(color: Colors.transparent),
            showCursor: false,
          ),
        ),
      ],
    );
  }
}

class _DigitCell extends StatelessWidget {
  const _DigitCell({required this.char, required this.filled, required this.active});

  final String char;
  final bool filled;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 60,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.s),
        border: Border.all(
          color: active ? AppColors.seed : AppColors.outline,
          width: active ? 2 : 1.5,
        ),
      ),
      child: Text(
        filled ? char : '',
        style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700),
      ),
    );
  }
}
