import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:network/network.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';

class AuthDiagnosticsPanel extends ConsumerWidget {
  const AuthDiagnosticsPanel({super.key, this.title = 'დიაგნოსტიკა'});

  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final diagnostics = ref.watch(networkDiagnosticsProvider);
    final tokenStore = ref.watch(tokenStoreProvider);

    return ValueListenableBuilder<NetworkDiagnosticsState>(
      valueListenable: diagnostics,
      builder: (context, network, _) {
        return FutureBuilder<String?>(
          future: tokenStore.read(),
          builder: (context, snapshot) {
            final tokenPresent =
                snapshot.connectionState == ConnectionState.done
                    ? snapshot.data?.trim().isNotEmpty == true
                    : network.tokenPresent;

            return Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Insets.m),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(Radii.m),
                border: Border.all(color: AppColors.outlineSubtle),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppType.label),
                  const SizedBox(height: Insets.s),
                  _DiagnosticsLine(
                    label: 'Last request URL',
                    value: network.lastRequestUrl ?? 'none',
                  ),
                  _DiagnosticsLine(
                    label: 'Status code',
                    value: network.lastResponseStatus?.toString() ?? 'none',
                  ),
                  _DiagnosticsLine(
                    label: 'Body excerpt',
                    value: network.lastResponseBodyExcerpt ?? 'none',
                  ),
                  _DiagnosticsLine(
                    label: 'Network exception',
                    value: network.lastNetworkException ?? 'none',
                  ),
                  _DiagnosticsLine(
                    label: 'Current route',
                    value: network.currentRoute ?? 'unknown',
                  ),
                  _DiagnosticsLine(
                    label: 'Token present',
                    value: tokenPresent == null
                        ? 'unknown'
                        : (tokenPresent ? 'yes' : 'no'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

class _DiagnosticsLine extends StatelessWidget {
  const _DiagnosticsLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.xs),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppType.caption),
          const SizedBox(height: 2),
          SelectableText(value, style: AppType.body),
        ],
      ),
    );
  }
}
