import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../shift/state/shift_controller.dart';

class DiagnosticsPage extends ConsumerWidget {
  const DiagnosticsPage({super.key});

  static const _buildAppName =
      String.fromEnvironment('HANGOVER_BUILD_APP_NAME');
  static const _buildVersionName =
      String.fromEnvironment('HANGOVER_BUILD_VERSION_NAME');
  static const _buildVersionCode =
      String.fromEnvironment('HANGOVER_BUILD_VERSION_CODE');
  static const _buildTimestamp =
      String.fromEnvironment('HANGOVER_BUILD_TIMESTAMP');
  static const _buildPackageId =
      String.fromEnvironment('HANGOVER_BUILD_PACKAGE_ID');
  static const _buildCommit = String.fromEnvironment('HANGOVER_BUILD_COMMIT');

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final env = ref.watch(envProvider);
    final shift = ref.watch(shiftProvider);
    final tokenFuture = ref.watch(tokenStoreProvider).read();

    return Scaffold(
      appBar: AppBar(title: const Text('Diagnostics')),
      body: SafeArea(
        child: FutureBuilder<String?>(
          future: tokenFuture,
          builder: (context, snapshot) {
            final tokenPresent =
                snapshot.connectionState == ConnectionState.done
                    ? snapshot.data != null && snapshot.data!.trim().isNotEmpty
                    : shift.authTokenPresent;

            return ListView(
              padding: const EdgeInsets.all(Insets.l),
              children: [
                _Row(label: 'API_BASE_URL', value: env.apiBaseUrl),
                _Row(
                  label: 'Build app',
                  value: _buildAppName.ifEmpty('Ride 360 Driver'),
                ),
                _Row(
                  label: 'Build number',
                  value: [
                    _buildVersionName.ifEmpty('0.1.0'),
                    _buildVersionCode.ifEmpty('unknown'),
                  ].join(' + '),
                ),
                _Row(
                  label: 'Build timestamp',
                  value: _buildTimestamp.ifEmpty('unknown'),
                ),
                _Row(
                  label: 'Package id',
                  value: _buildPackageId.ifEmpty('unknown'),
                ),
                _Row(
                  label: 'Git commit',
                  value: _buildCommit.ifEmpty('unknown'),
                ),
                _Row(
                  label: 'Maps key present',
                  value: env.googleMapsKey.trim().isEmpty ? 'no' : 'yes',
                ),
                _Row(
                  label: 'Auth token present',
                  value: _boolOrUnknown(tokenPresent),
                ),
                _Row(label: 'Last driver action', value: shift.lastAction),
                _Row(
                  label: 'Last driver API endpoint',
                  value: shift.lastApiEndpoint ?? 'none',
                ),
                _Row(
                  label: 'Last driver API status',
                  value: shift.lastApiStatus?.toString() ?? 'none',
                ),
                _Row(
                  label: 'Last driver API error',
                  value: shift.lastApiError ?? shift.error ?? 'none',
                ),
                _Row(
                  label: 'Location status',
                  value: shift.locationStatus,
                ),
                _Row(
                  label: 'Lat/lng available',
                  value: shift.locationAvailable ? 'yes' : 'no',
                ),
                _Row(
                  label: 'Driver online state',
                  value: shift.online ? 'online' : 'offline',
                ),
                _Row(
                  label: 'Driver profile present',
                  value: _boolOrUnknown(shift.driverProfilePresent),
                ),
                _Row(
                  label: 'Driver profile/approval state',
                  value: shift.driverProfileState,
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  static String _boolOrUnknown(bool? value) {
    if (value == null) return 'unknown';
    return value ? 'yes' : 'no';
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: Insets.s),
      padding: const EdgeInsets.all(Insets.m),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.m),
        border: Border.all(color: AppColors.outlineSubtle),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppType.label),
          const SizedBox(height: 4),
          SelectableText(value, style: AppType.body),
        ],
      ),
    );
  }
}

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
