import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:network/network.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../profile/state/driver_profile_controller.dart';
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
    final me = ref.watch(driverMeProvider);
    final diagnostics = ref.watch(networkDiagnosticsProvider);
    final tokenFuture = ref.watch(tokenStoreProvider).read();
    final deviceFuture = ref.watch(tokenStoreProvider).readDeviceUuid();

    return Scaffold(
      appBar: AppBar(title: const Text('Diagnostics')),
      body: SafeArea(
        child: ValueListenableBuilder<NetworkDiagnosticsState>(
          valueListenable: diagnostics,
          builder: (context, network, _) {
            return FutureBuilder<(String?, String?)>(
              future: Future.wait<String?>([tokenFuture, deviceFuture]).then(
                (values) => (values[0], values[1]),
              ),
              builder: (context, snapshot) {
                final token = snapshot.data?.$1;
                final tokenPresent =
                    snapshot.connectionState == ConnectionState.done
                        ? token != null && token.trim().isNotEmpty
                        : network.tokenPresent ?? shift.authTokenPresent;
                final deviceUuid = snapshot.data?.$2;

                return ListView(
                  padding: const EdgeInsets.all(Insets.l),
                  children: [
                    _Row(label: 'API_BASE_URL', value: env.apiBaseUrl),
                    _Row(
                      label: 'Last request method',
                      value: network.lastRequestMethod ?? 'none',
                    ),
                    _Row(
                      label: 'Last request URL',
                      value: network.lastRequestUrl ?? 'none',
                    ),
                    _Row(
                      label: 'Last response status',
                      value: network.lastResponseStatus?.toString() ?? 'none',
                    ),
                    _Row(
                      label: 'Last response body excerpt',
                      value: network.lastResponseBodyExcerpt ?? 'none',
                    ),
                    _Row(
                      label: 'Last network exception',
                      value: network.lastNetworkException ?? 'none',
                    ),
                    _Row(
                      label: 'Auth token present',
                      value: _boolOrUnknown(tokenPresent),
                    ),
                    _Row(
                      label: 'Last token abilities',
                      value: network.lastAuthAbilities.isEmpty
                          ? 'unknown'
                          : network.lastAuthAbilities.join(', '),
                    ),
                    _Row(
                      label: 'Last auth user type',
                      value: network.lastAuthUserType ?? 'unknown',
                    ),
                    _Row(
                      label: 'Device UUID',
                      value: deviceUuid ?? 'unknown',
                    ),
                    _Row(
                      label: 'Platform',
                      value: defaultTargetPlatform.name,
                    ),
                    _Row(
                      label: 'App version',
                      value: _buildVersionName.ifEmpty('0.1.0'),
                    ),
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
                    ..._driverContextRows(me),
                  ],
                );
              },
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

  static List<Widget> _driverContextRows(AsyncValue<DriverMe> value) {
    return value.when(
      loading: () => const [
        _Row(label: 'Driver context', value: 'loading'),
      ],
      error: (error, _) => [
        _Row(label: 'Driver context error', value: error.toString()),
      ],
      data: (me) {
        final context = me.context;
        return [
          _Row(label: 'Driver runtime state', value: context.state.name),
          _Row(
            label: 'Driver profile status',
            value: context.driverProfileStatus ?? 'none',
          ),
          _Row(
            label: 'Application status',
            value: context.applicationStatus ?? 'none',
          ),
          _Row(
            label: 'Vehicle status',
            value: context.vehicleStatus ?? 'none',
          ),
          _Row(
            label: 'Can go online',
            value: context.canGoOnline ? 'yes' : 'no',
          ),
          _Row(
            label: 'Cannot go online reason',
            value: context.reasonIfCannotGoOnline ?? 'none',
          ),
        ];
      },
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
