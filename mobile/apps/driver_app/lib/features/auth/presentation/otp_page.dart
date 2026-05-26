import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:network/network.dart';
import 'package:ui_kit/ui_kit.dart';
import 'package:uuid/uuid.dart';

import '../../../di/locator.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';
import '../application/driver_auth_flow.dart';
import '../application/driver_post_login_router.dart';

class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({
    super.key,
    required this.phone,
    required this.flow,
  });

  final String phone;
  final DriverAuthFlow flow;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  final _controller = TextEditingController();
  bool _busy = false;
  String? _error;
  bool _showRegistrationCta = false;

  Future<void> _verify() async {
    setState(() {
      _busy = true;
      _error = null;
      _showRegistrationCta = false;
    });
    try {
      final platform =
          Theme.of(context).platform == TargetPlatform.iOS ? 'ios' : 'android';
      final TokenStore store = ref.read(tokenStoreProvider);
      var deviceUuid = await store.readDeviceUuid();
      if (deviceUuid == null) {
        deviceUuid = const Uuid().v4();
        await store.writeDeviceUuid(deviceUuid);
      }

      final auth = await ref.read(authRepositoryProvider).verifyOtp(
            phone: widget.phone,
            code: _controller.text.trim(),
            purpose: widget.flow.otpPurpose,
            deviceUuid: deviceUuid,
            platform: platform,
            appVersion: '0.1.0',
          );
      if (!isDriverLoginAbility(auth.abilities)) {
        await store.clear();
        if (!mounted) return;
        setState(() {
          _error = widget.flow.roleMismatchMessage;
          _showRegistrationCta = widget.flow == DriverAuthFlow.login;
        });
        return;
      }

      final me = await ref.read(driverProfileRepositoryProvider).me();
      if (!mounted) return;
      context.go(routeForDriverContextAfterOtp(me.context, widget.flow));
    } on ApiError catch (e) {
      setState(() => _error = driverLoginErrorMessage(e));
    } catch (e) {
      setState(() => _error = driverLoginErrorMessage(e));
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
        title: Text(widget.flow.otpTitle),
        leading: IconButton(
          onPressed: () =>
              context.go('/auth/phone?mode=${widget.flow.queryValue}'),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(Insets.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: Insets.l),
            const DriverBuildIdentityLabel(),
            const SizedBox(height: Insets.xxl),
            Text('კოდი გაიგზავნა ნომერზე ${widget.phone}',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: Insets.l),
            AppTextField(
              controller: _controller,
              label: '6-ნიშნა კოდი',
              keyboardType: TextInputType.number,
              autofocus: true,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            ),
            if (_error != null) ...[
              const SizedBox(height: Insets.s),
              Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
              if (_showRegistrationCta) ...[
                const SizedBox(height: Insets.s),
                OutlinedButton(
                  onPressed: () => context.go('/auth/phone?mode=registration'),
                  child: const Text('მძღოლად რეგისტრაცია'),
                ),
              ],
            ],
            const Spacer(),
            PrimaryButton(
              label: widget.flow.otpActionLabel,
              onPressed: _verify,
              busy: _busy,
            ),
            const SizedBox(height: Insets.s),
            TextButton.icon(
              onPressed: _busy ? null : () => context.push('/diagnostics'),
              icon: const Icon(Icons.info_outline_rounded),
              label: const Text('დიაგნოსტიკა'),
            ),
          ],
        ),
      ),
    );
  }
}
