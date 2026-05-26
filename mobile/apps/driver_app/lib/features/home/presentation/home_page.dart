import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../auth/application/driver_post_login_router.dart';
import '../../demo/presentation/demo_stepper.dart';
import '../../profile/state/driver_profile_controller.dart';
import '../../ride/presentation/active_ride_sheet.dart';
import '../../ride/presentation/incoming_offer_sheet.dart';
import '../../shift/state/shift_controller.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shift = ref.watch(shiftProvider);
    final me = ref.watch(driverMeProvider);
    ref.listen<String?>(
      shiftProvider.select((value) => value.error),
      (previous, next) {
        if (next == null || next == previous) return;
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(next)));
      },
    );

    return me.when(
      loading: () => const Scaffold(body: LoadingState()),
      error: (error, _) {
        final copy = _homeErrorCopy(error);
        return _DriverStateScaffold(
          title: copy.title,
          body: copy.body,
          primaryLabel: copy.requiresLogin ? 'ხელახლა შესვლა' : 'თავიდან ცდა',
          onPrimary: () async {
            if (copy.requiresLogin) {
              await ref.read(tokenStoreProvider).clear();
              if (context.mounted) context.go('/auth/phone');
              return;
            }
            ref.invalidate(driverMeProvider);
          },
          secondaryLabel: 'Diagnostics',
          onSecondary: () => context.push('/diagnostics'),
        );
      },
      data: (profile) {
        final driverContext = profile.context;
        if (!driverContext.canShowDashboard) {
          return _DriverOnboardingScaffold(context: driverContext);
        }
        return _DriverDashboard(
          shift: shift,
          mapProvider: ref.watch(mapProviderProvider),
          driverContext: driverContext,
        );
      },
    );
  }
}

class _HomeErrorCopy {
  const _HomeErrorCopy({
    required this.title,
    required this.body,
    this.requiresLogin = false,
  });

  final String title;
  final String body;
  final bool requiresLogin;
}

_HomeErrorCopy _homeErrorCopy(Object error) {
  final apiError = apiErrorFrom(error);
  if (apiError == null) {
    final text = error.toString();
    final lower = text.toLowerCase();
    if (lower.contains('timeout') || lower.contains('timed out')) {
      return const _HomeErrorCopy(
        title: 'ქსელის დრო ამოიწურა',
        body:
            'გადაამოწმეთ ინტერნეტი და სცადეთ თავიდან. დეტალები Diagnostics-ში ჩანს.',
      );
    }
    return _HomeErrorCopy(
      title: 'სერვერთან კავშირი ვერ მოხერხდა',
      body: 'დეტალები Diagnostics-ში ჩანს.\n$text',
    );
  }

  final detail =
      'HTTP ${apiError.httpStatus ?? 'unknown'} · ${apiError.code}\n${apiError.message}';
  return switch (apiError.httpStatus) {
    401 => _HomeErrorCopy(
        title: 'ავტორიზაცია ვერ დადასტურდა',
        body: detail,
        requiresLogin: true,
      ),
    403 => _HomeErrorCopy(
        title: 'არასწორი ავტორიზაციის როლი',
        body:
            '$detail\nDriver აპისთვის საჭიროა driver ან driver:onboarding token.',
        requiresLogin: true,
      ),
    404 => _HomeErrorCopy(
        title: 'სერვერის endpoint ვერ მოიძებნა',
        body: '$detail\nგადაამოწმეთ Diagnostics-ში ბოლო request URL.',
      ),
    500 || 502 || 503 => _HomeErrorCopy(
        title: 'სერვერის დროებითი შეცდომა',
        body: detail,
      ),
    _ => _HomeErrorCopy(
        title: 'მძღოლის პროფილის ჩატვირთვა ვერ მოხერხდა',
        body: detail,
      ),
  };
}

class _DriverDashboard extends ConsumerWidget {
  const _DriverDashboard({
    required this.shift,
    required this.mapProvider,
    required this.driverContext,
  });

  final ShiftState shift;
  final MapProvider mapProvider;
  final DriverContext driverContext;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: mapProvider.mapWidget(
              initialCenter: shift.position,
              myLocationEnabled: shift.locationStatus == 'granted',
            ),
          ),
          SafeArea(
            child: Padding(
              padding:
                  const EdgeInsets.fromLTRB(Insets.l, Insets.l, Insets.l, 0),
              child: Column(
                children: [
                  if (driverContext.canShowShiftControls)
                    _OnlineToggleBar(shift: shift),
                  const SizedBox(height: Insets.s),
                  if (shift.error != null)
                    _DriverStateCard(shift: shift)
                  else if (shift.locationStatus != 'permission not asked' &&
                      shift.locationStatus != 'granted')
                    _DriverStateCard(shift: shift),
                  if (shift.error != null ||
                      (shift.locationStatus != 'permission not asked' &&
                          shift.locationStatus != 'granted'))
                    const SizedBox(height: Insets.s),
                  if (driverContext.canShowEarnings)
                    Align(
                      alignment: Alignment.centerRight,
                      child: _EarningsBadge(
                        amount: double.tryParse(
                              driverContext.todayEarnings ?? '0',
                            ) ??
                            0,
                        currency: 'GEL',
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (shift.activeRide != null && !shift.activeRide!.status.isTerminal)
            const Align(
                alignment: Alignment.bottomCenter, child: ActiveRideSheet())
          else if (shift.activeRide != null &&
              shift.activeRide!.status == RideStatus.completed)
            Align(
                alignment: Alignment.bottomCenter,
                child: _CompletedSheet(ride: shift.activeRide!))
          else if (shift.online)
            const Align(
                alignment: Alignment.bottomCenter, child: _WaitingForRide()),
          if (shift.pendingOffer != null)
            Positioned.fill(
                child: IncomingOfferSheet(offer: shift.pendingOffer!)),
          const Align(
              alignment: Alignment.topCenter, child: DriverDemoStepper()),
        ],
      ),
    );
  }
}

class _DriverOnboardingScaffold extends ConsumerWidget {
  const _DriverOnboardingScaffold({required this.context});

  final DriverContext context;

  @override
  Widget build(BuildContext pageContext, WidgetRef ref) {
    final title = switch (context.state) {
      DriverRuntimeState.noDriverProfile => 'მძღოლის პროფილი არ არის ნაპოვნი',
      DriverRuntimeState.applicationDraft => 'განაცხადი დასასრულებელია',
      DriverRuntimeState.applicationPending => 'განაცხადი განხილვაშია',
      DriverRuntimeState.applicationRejected => 'განაცხადი უარყოფილია',
      DriverRuntimeState.approvedMissingVehicle =>
        'ტრანსპორტის მონაცემები დასამატებელია',
      DriverRuntimeState.suspended => 'მძღოლის პროფილი შეჩერებულია',
      _ => 'მძღოლის პროფილი არ არის აქტიური',
    };
    final body = switch (context.state) {
      DriverRuntimeState.applicationPending =>
        'თქვენი განაცხადი მიღებულია და ადმინისტრატორის შემოწმებას ელოდება.',
      DriverRuntimeState.applicationRejected => context.rejectionReason ??
          'გთხოვთ შეასწოროთ განაცხადი და გაგზავნოთ თავიდან.',
      DriverRuntimeState.approvedMissingVehicle =>
        'ონლაინ გასვლამდე საჭიროა აქტიური ტრანსპორტის დამატება ან დამტკიცება.',
      DriverRuntimeState.suspended =>
        'დამატებითი ინფორმაციისთვის დაუკავშირდით მხარდაჭერას.',
      _ => 'მძღოლად მუშაობისთვის შეავსეთ განაცხადი და დაელოდეთ დამტკიცებას.',
    };

    return _DriverStateScaffold(
      title: title,
      body: body,
      primaryLabel: context.state == DriverRuntimeState.applicationPending
          ? 'ჩემი განაცხადი'
          : 'მძღოლის განაცხადის შევსება',
      onPrimary: () => pageContext.push('/application'),
      secondaryLabel: 'Diagnostics',
      onSecondary: () => pageContext.push('/diagnostics'),
    );
  }
}

class _DriverStateScaffold extends StatelessWidget {
  const _DriverStateScaffold({
    required this.title,
    required this.body,
    required this.primaryLabel,
    required this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
  });

  final String title;
  final String body;
  final String primaryLabel;
  final VoidCallback onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Row(
                children: [
                  BrandLogo(size: BrandLogoSize.m),
                  Spacer(),
                  StatusPill(label: 'Driver', tone: StatusTone.accent),
                ],
              ),
              const SizedBox(height: Insets.xxl),
              Text(title, style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: Insets.s),
              Text(body, style: Theme.of(context).textTheme.bodyLarge),
              const Spacer(),
              PrimaryButton(label: primaryLabel, onPressed: onPrimary),
              if (secondaryLabel != null && onSecondary != null) ...[
                const SizedBox(height: Insets.s),
                OutlinedButton(
                  onPressed: onSecondary,
                  child: Text(secondaryLabel!),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _OnlineToggleBar extends ConsumerWidget {
  const _OnlineToggleBar({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isOnline = shift.online;
    final canTap = !shift.isWorking;
    Future<void> toggle() => isOnline
        ? ref.read(shiftProvider.notifier).goOffline()
        : ref.read(shiftProvider.notifier).goOnline();

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(Radii.l),
      elevation: 6,
      shadowColor: const Color(0x14000000),
      child: InkWell(
        borderRadius: BorderRadius.circular(Radii.l),
        onTap: canTap ? toggle : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: Insets.l,
            vertical: Insets.m,
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: isOnline ? AppColors.seed : AppColors.surfaceVariant,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Icon(
                  isOnline
                      ? Icons.flash_on_rounded
                      : Icons.power_settings_new_rounded,
                  color: isOnline ? Colors.white : AppColors.inkSoft,
                  size: 22,
                ),
              ),
              const SizedBox(width: Insets.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shift.isWorking
                          ? (isOnline
                              ? 'Going offline...'
                              : 'Starting shift...')
                          : (isOnline
                              ? 'You are online'
                              : 'Tap to start your shift'),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      isOnline
                          ? 'Tbilisi · accepting rides'
                          : "You won't receive offers while offline",
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Diagnostics',
                onPressed: () {
                  ref
                      .read(shiftProvider.notifier)
                      .recordAction('diagnostics tap');
                  context.push('/diagnostics');
                },
                icon: const Icon(Icons.info_outline_rounded),
              ),
              Switch.adaptive(
                value: isOnline,
                activeThumbColor: AppColors.seed,
                onChanged: canTap ? (_) => toggle() : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DriverStateCard extends ConsumerWidget {
  const _DriverStateCard({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locationBlocked = shift.locationStatus == 'denied' ||
        shift.locationStatus == 'restricted' ||
        shift.locationStatus == 'service disabled';
    final message = shift.error ??
        (locationBlocked
            ? 'ლოკაციის ნებართვა საჭიროა'
            : 'ცვლის დაწყება ვერ მოხერხდა');

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Insets.m),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.m),
        border: Border.all(color: AppColors.outlineSubtle),
        boxShadow: const [BoxShadow(blurRadius: 16, color: Color(0x12000000))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.warning_amber_rounded,
                  color: AppColors.warning, size: 20),
              const SizedBox(width: Insets.s),
              Expanded(
                child: Text(
                  message,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.xs),
          Text(
            'Status: ${shift.locationStatus} · API: ${shift.lastApiStatus?.toString() ?? 'none'}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: Insets.s),
          Row(
            children: [
              if (locationBlocked)
                TextButton.icon(
                  onPressed: () =>
                      ref.read(shiftProvider.notifier).openLocationSettings(),
                  icon: const Icon(Icons.settings_rounded),
                  label: const Text('Settings'),
                ),
              TextButton.icon(
                onPressed: () {
                  ref
                      .read(shiftProvider.notifier)
                      .recordAction('diagnostics tap');
                  context.push('/diagnostics');
                },
                icon: const Icon(Icons.info_outline_rounded),
                label: const Text('Diagnostics'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _EarningsBadge extends StatelessWidget {
  const _EarningsBadge({required this.amount, required this.currency});

  final double amount;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:
          const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s),
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.account_balance_wallet_rounded,
              color: AppColors.accent, size: 14),
          const SizedBox(width: 6),
          Text(
            'Today · ${amount.toStringAsFixed(2)} $currency',
            style: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _WaitingForRide extends StatelessWidget {
  const _WaitingForRide();

  @override
  Widget build(BuildContext context) {
    return BottomSheetCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              const StatusPill(
                  label: 'Live', tone: StatusTone.success, pulse: true),
              const SizedBox(width: Insets.s),
              Text('Waiting for ride requests',
                  style: Theme.of(context).textTheme.titleLarge),
            ],
          ),
          const SizedBox(height: Insets.xs),
          Text('12 scooters online nearby · Saburtalo is busiest right now.',
              style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: Insets.l),
          const Row(
            children: [
              _ShiftStat(label: 'Trips', value: '6'),
              SizedBox(width: Insets.s),
              _ShiftStat(label: 'Hours', value: '3h 12m'),
              SizedBox(width: Insets.s),
              _ShiftStat(label: 'Rating', value: '★ 4.91'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ShiftStat extends StatelessWidget {
  const _ShiftStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: Insets.m),
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(Radii.m),
        ),
        child: Column(
          children: [
            Text(label, style: Theme.of(context).textTheme.labelMedium),
            const SizedBox(height: 2),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}

class _CompletedSheet extends ConsumerWidget {
  const _CompletedSheet({required this.ride});

  final Ride ride;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final amount = ride.finalAmount ?? ride.quotedAmount;
    return BottomSheetCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          SuccessState(
            headline: 'Ride complete',
            body: 'Earnings paid to wallet',
            amount: amount,
            currency: ride.currency,
          ),
          const SizedBox(height: Insets.m),
          PrimaryButton(
            label: 'Continue',
            onPressed: () =>
                ref.read(shiftProvider.notifier).dismissCompletedRide(),
          ),
        ],
      ),
    );
  }
}
