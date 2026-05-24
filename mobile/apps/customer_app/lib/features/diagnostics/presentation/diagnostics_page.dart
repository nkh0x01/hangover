import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../state/diagnostics_controller.dart';

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
    final diagnostics = ref.watch(diagnosticsProvider);
    final tokenFuture = ref.watch(tokenStoreProvider).read();

    return Scaffold(
      appBar: AppBar(title: const Text('Diagnostics')),
      body: SafeArea(
        child: FutureBuilder<String?>(
          future: tokenFuture,
          builder: (context, snapshot) {
            final authState = snapshot.connectionState == ConnectionState.done
                ? (snapshot.data == null ? 'signed out' : 'token present')
                : diagnostics.authState;

            return ListView(
              padding: const EdgeInsets.all(Insets.l),
              children: [
                _Row(label: 'API_BASE_URL', value: env.apiBaseUrl),
                _Row(
                    label: 'Build app',
                    value: _buildAppName.ifEmpty('Ride 360')),
                _Row(
                  label: 'Build number',
                  value: [
                    _buildVersionName.ifEmpty('0.1.0'),
                    _buildVersionCode.ifEmpty('1'),
                  ].join(' + '),
                ),
                _Row(
                    label: 'Build timestamp',
                    value: _buildTimestamp.ifEmpty('unknown')),
                _Row(
                    label: 'Package id',
                    value: _buildPackageId.ifEmpty('unknown')),
                _Row(
                    label: 'Git commit',
                    value: _buildCommit.ifEmpty('unknown')),
                _Row(label: 'Auth state', value: authState),
                _Row(
                  label: 'Maps key present',
                  value: env.googleMapsKey.trim().isEmpty ? 'no' : 'yes',
                ),
                _Row(
                    label: 'Location permission',
                    value: diagnostics.locationStatus),
                _Row(
                  label: 'Last API status',
                  value: diagnostics.lastApiStatus?.toString() ?? 'none',
                ),
                _Row(
                    label: 'Last API error',
                    value: diagnostics.lastError ?? 'none'),
                _Row(label: 'Last tap/action', value: diagnostics.lastAction),
              ],
            );
          },
        ),
      ),
    );
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
