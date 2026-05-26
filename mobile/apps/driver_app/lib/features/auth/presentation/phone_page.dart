import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../demo/state/demo_mode_controller.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';
import '../application/driver_auth_flow.dart';

class PhonePage extends ConsumerStatefulWidget {
  const PhonePage({super.key, required this.flow});

  final DriverAuthFlow flow;

  @override
  ConsumerState<PhonePage> createState() => _PhonePageState();
}

class _PhonePageState extends ConsumerState<PhonePage> {
  final _controller = TextEditingController(text: '+995');
  bool _busy = false;
  String? _error;

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).requestOtp(
            phone: _controller.text.trim(),
            purpose: widget.flow.otpPurpose,
          );
      if (!mounted) return;
      context.go(
        '/auth/otp?mode=${widget.flow.queryValue}&phone=${Uri.encodeComponent(_controller.text.trim())}',
      );
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
      appBar: AppBar(
        title: Text(widget.flow.phoneTitle),
        leading: IconButton(
          onPressed: () => context.go('/welcome'),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Insets.l),
              const Row(
                children: [
                  BrandLogo(size: BrandLogoSize.m),
                  Spacer(),
                  StatusPill(label: 'Driver', tone: StatusTone.accent),
                ],
              ),
              const SizedBox(height: Insets.s),
              const DriverBuildIdentityLabel(),
              const SizedBox(height: Insets.xl),
              Text(widget.flow.phoneTitle,
                  style: Theme.of(context).textTheme.headlineLarge),
              const SizedBox(height: Insets.xs),
              Text(
                widget.flow.phoneSubtitle,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: Insets.xxl),
              Text('ტელეფონის ნომერი',
                  style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: Insets.s),
              AppTextField(
                  controller: _controller, keyboardType: TextInputType.phone),
              if (_error != null) ...[
                const SizedBox(height: Insets.s),
                Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error)),
              ],
              const Spacer(),
              PrimaryButton(
                label: widget.flow.phoneTitle,
                onPressed: _send,
                busy: _busy,
              ),
              const SizedBox(height: Insets.s),
              OutlinedButton(
                onPressed: _busy
                    ? null
                    : () => context.go(
                          widget.flow == DriverAuthFlow.login
                              ? '/auth/phone?mode=registration'
                              : '/auth/phone?mode=login',
                        ),
                child: Text(
                  widget.flow == DriverAuthFlow.login
                      ? 'მძღოლად რეგისტრაცია'
                      : 'უკვე გაქვთ ანგარიში? შესვლა',
                ),
              ),
              const SizedBox(height: Insets.s),
              TextButton.icon(
                onPressed: _busy ? null : () => context.push('/diagnostics'),
                icon: const Icon(Icons.info_outline_rounded),
                label: const Text('დიაგნოსტიკა'),
              ),
              // Preview entry. Available in dev AND staging so QA can
              // demo from an installed staging APK without backend
              // round-trips. Hidden in prod by the env.isProd gate.
              if (!ref.watch(envProvider).isProd) ...[
                const SizedBox(height: Insets.s),
                OutlinedButton.icon(
                  onPressed: () {
                    ref.read(driverDemoProvider.notifier).activate();
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
              ],
            ],
          ),
        ),
      ),
    );
  }
}
