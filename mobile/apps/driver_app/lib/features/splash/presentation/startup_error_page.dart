import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../diagnostics/presentation/auth_diagnostics_panel.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';
import '../../profile/state/driver_profile_controller.dart';

class StartupErrorPage extends ConsumerWidget {
  const StartupErrorPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final network = ref.watch(networkDiagnosticsProvider).value;
    final status = network.lastResponseStatus;
    final title = switch (status) {
      401 => 'სესიის ვადა ამოიწურა',
      403 => 'წვდომა შეზღუდულია',
      404 => 'სერვერის endpoint ვერ მოიძებნა',
      500 || 502 || 503 => 'სერვერის დროებითი შეცდომა',
      _ when network.lastNetworkException != null => 'ქსელის შეცდომა',
      _ => 'საწყისი შემოწმება ვერ დასრულდა',
    };
    final body = switch (status) {
      401 => 'გთხოვთ გაასუფთავოთ სესია და თავიდან შეხვიდეთ.',
      403 => 'ამ ანგარიშს Driver აპში შესვლის უფლება არ აქვს.',
      404 => 'აპმა ვერ იპოვა საჭირო endpoint. გადაამოწმეთ დეტალები ქვემოთ.',
      500 || 502 || 503 => 'სერვერმა დროებითი შეცდომა დააბრუნა.',
      _ when network.lastNetworkException != null =>
        'გადაამოწმეთ ინტერნეტი და სცადეთ თავიდან.',
      _ => 'დეტალები ქვემოთ ჩანს.',
    };

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: ListView(
            children: [
              const Row(
                children: [
                  BrandLogo(size: BrandLogoSize.m),
                  Spacer(),
                  StatusPill(label: 'Driver', tone: StatusTone.accent),
                ],
              ),
              const SizedBox(height: Insets.s),
              const DriverBuildIdentityLabel(),
              const SizedBox(height: Insets.xxl),
              Text(title, style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: Insets.s),
              Text(body, style: Theme.of(context).textTheme.bodyLarge),
              const SizedBox(height: Insets.l),
              const AuthDiagnosticsPanel(),
              const SizedBox(height: Insets.xl),
              PrimaryButton(
                label: 'სესიის გასუფთავება',
                onPressed: () async {
                  await ref.read(tokenStoreProvider).clear();
                  ref.invalidate(driverMeProvider);
                  if (context.mounted) context.go('/welcome');
                },
              ),
              const SizedBox(height: Insets.s),
              OutlinedButton(
                onPressed: () => context.go('/'),
                child: const Text('თავიდან ცდა'),
              ),
              const SizedBox(height: Insets.s),
              TextButton.icon(
                onPressed: () => context.push('/diagnostics'),
                icon: const Icon(Icons.info_outline_rounded),
                label: const Text('დიაგნოსტიკა'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
