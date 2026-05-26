import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../di/locator.dart';

class RouteDiagnosticsMarker extends ConsumerStatefulWidget {
  const RouteDiagnosticsMarker({
    super.key,
    required this.route,
    required this.child,
  });

  final String route;
  final Widget child;

  @override
  ConsumerState<RouteDiagnosticsMarker> createState() =>
      _RouteDiagnosticsMarkerState();
}

class _RouteDiagnosticsMarkerState
    extends ConsumerState<RouteDiagnosticsMarker> {
  @override
  void initState() {
    super.initState();
    _record();
  }

  @override
  void didUpdateWidget(RouteDiagnosticsMarker oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.route != widget.route) {
      _record();
    }
  }

  void _record() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ref.read(networkDiagnosticsProvider).recordCurrentRoute(widget.route);
    });
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
