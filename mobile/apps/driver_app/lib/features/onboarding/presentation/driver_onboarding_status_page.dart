import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../diagnostics/presentation/auth_diagnostics_panel.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';
import '../../profile/state/driver_profile_controller.dart';

class DriverOnboardingStatusPage extends ConsumerWidget {
  const DriverOnboardingStatusPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(driverMeProvider);

    return me.when(
      loading: () => const Scaffold(
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
      ),
      error: (error, _) => _OnboardingStatusScaffold(
        statusText: 'მძღოლის განაცხადის მდგომარეობა ვერ ჩაიტვირთა.',
        detail: error.toString(),
        showDiagnosticsDetails: true,
      ),
      data: (me) {
        final driverContext = me.context;
        if (driverContext.canShowDashboard) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (context.mounted) context.go('/home');
          });
          return const Scaffold(body: LoadingState());
        }

        return _OnboardingStatusScaffold(
          statusText: _statusText(driverContext),
          detail: _statusDetail(driverContext),
          primaryLabel: _primaryLabel(driverContext),
        );
      },
    );
  }

  static String _primaryLabel(DriverContext context) {
    if (context.needsApplication ||
        context.canSubmitApplication ||
        context.state == DriverRuntimeState.noDriverProfile) {
      return 'განაცხადის შევსება';
    }
    return 'განაცხადის გაგრძელება';
  }

  static String _statusText(DriverContext context) {
    final applicationStatus = context.applicationStatus;
    if (context.state == DriverRuntimeState.applicationRejected ||
        applicationStatus == 'rejected') {
      return context.rejectionReason ?? 'განაცხადი უარყოფილია';
    }
    if (applicationStatus == 'pending' ||
        applicationStatus == 'submitted' ||
        context.state == DriverRuntimeState.applicationPending) {
      return 'განაცხადი განხილვაშია';
    }
    if (applicationStatus == 'needs_changes' ||
        context.state == DriverRuntimeState.applicationDraft) {
      return 'განაცხადს სჭირდება ცვლილება';
    }
    if (context.state == DriverRuntimeState.approvedMissingVehicle) {
      return 'ტრანსპორტის მონაცემები დასამატებელია';
    }
    if (context.state == DriverRuntimeState.suspended) {
      return 'მძღოლის პროფილი შეჩერებულია';
    }
    if (context.needsApplication ||
        context.canSubmitApplication ||
        !context.hasDriverProfile) {
      return 'განაცხადი ჯერ არ არის შევსებული';
    }
    return 'მძღოლის პროფილი ჯერ არ არის აქტიური';
  }

  static String? _statusDetail(DriverContext context) {
    if (context.reasonIfCannotGoOnline == null &&
        context.missingRequiredFields.isEmpty &&
        context.missingDocuments.isEmpty) {
      return null;
    }
    final parts = <String>[
      if (context.reasonIfCannotGoOnline != null)
        'მიზეზი: ${context.reasonIfCannotGoOnline}',
      if (context.missingRequiredFields.isNotEmpty)
        'დასამატებელი ველები: ${context.missingRequiredFields.join(', ')}',
      if (context.missingDocuments.isNotEmpty)
        'დასამატებელი დოკუმენტები: ${context.missingDocuments.join(', ')}',
    ];
    return parts.join('\n');
  }
}

class _OnboardingStatusScaffold extends ConsumerWidget {
  const _OnboardingStatusScaffold({
    required this.statusText,
    this.detail,
    this.primaryLabel = 'განაცხადის გაგრძელება',
    this.showDiagnosticsDetails = false,
  });

  final String statusText;
  final String? detail;
  final String primaryLabel;
  final bool showDiagnosticsDetails;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
              Text(
                'მძღოლის განაცხადი',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: Insets.s),
              Text(statusText, style: Theme.of(context).textTheme.titleMedium),
              if (detail != null && detail!.trim().isNotEmpty) ...[
                const SizedBox(height: Insets.s),
                Text(detail!, style: Theme.of(context).textTheme.bodyMedium),
              ],
              if (showDiagnosticsDetails) ...[
                const SizedBox(height: Insets.l),
                const AuthDiagnosticsPanel(),
              ],
              const SizedBox(height: Insets.xxl),
              PrimaryButton(
                label: primaryLabel,
                onPressed: () => context.push('/application'),
              ),
              const SizedBox(height: Insets.s),
              OutlinedButton(
                onPressed: () => _clearSession(
                  ref,
                  onDone: () {
                    if (context.mounted) {
                      context.go('/auth/phone?mode=login');
                    }
                  },
                ),
                child: const Text('შესვლა სხვა ნომრით'),
              ),
              const SizedBox(height: Insets.s),
              OutlinedButton(
                onPressed: () => context.go('/welcome'),
                child: const Text('მთავარ არჩევანზე დაბრუნება'),
              ),
              const SizedBox(height: Insets.s),
              OutlinedButton(
                onPressed: () => _clearSession(
                  ref,
                  onDone: () {
                    if (context.mounted) context.go('/welcome');
                  },
                ),
                child: const Text('სესიის გასუფთავება'),
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

  static Future<void> _clearSession(
    WidgetRef ref, {
    required VoidCallback onDone,
  }) async {
    await ref.read(tokenStoreProvider).clear();
    ref.invalidate(driverMeProvider);
    onDone();
  }
}
