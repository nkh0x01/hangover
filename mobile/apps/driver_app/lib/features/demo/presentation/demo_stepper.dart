import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../state/demo_mode_controller.dart';

/// Dev-only floating overlay for the driver preview. Renders nothing
/// when [DriverDemoState.enabled] is false.
class DriverDemoStepper extends ConsumerWidget {
  const DriverDemoStepper({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(driverDemoProvider);
    if (!state.enabled) return const SizedBox.shrink();

    final controller = ref.read(driverDemoProvider.notifier);
    final next = state.stage.next;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(Insets.l, Insets.s, Insets.l, 0),
        child: Material(
          color: Colors.black.withValues(alpha: 0.82),
          elevation: 8,
          borderRadius: BorderRadius.circular(Radii.pill),
          child: Padding(
            padding:
                const EdgeInsets.symmetric(horizontal: Insets.l, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.visibility_rounded,
                    size: 16, color: Colors.white),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    'Preview · ${state.stage.label}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: Insets.m),
                if (next != null)
                  _StepperButton(
                    label: 'Next: ${next.label}',
                    onTap: controller.advance,
                  )
                else
                  _StepperButton(
                    label: 'Restart',
                    onTap: () => controller.jumpTo(DriverDemoStage.offlineIdle),
                  ),
                const SizedBox(width: 6),
                _StepperButton(
                  label: 'Exit',
                  outlined: true,
                  onTap: () {
                    controller.exit();
                    context.go('/welcome');
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  const _StepperButton(
      {required this.label, required this.onTap, this.outlined = false});

  final String label;
  final VoidCallback onTap;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Radii.pill),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: outlined ? Colors.transparent : Colors.white,
          border: outlined ? Border.all(color: Colors.white, width: 1) : null,
          borderRadius: BorderRadius.circular(Radii.pill),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: outlined ? Colors.white : Colors.black,
            fontWeight: FontWeight.w700,
            fontSize: 12,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }
}
