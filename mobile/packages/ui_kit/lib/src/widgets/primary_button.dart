import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Tall, single-tap primary CTA. Phase 1.6: bumped height to 60 logical
/// pixels (matches the driver-app one-handed target), added optional
/// leading icon, and switched to the explicit colour tokens so primary
/// actions render the same regardless of where they're nested.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.busy = false,
    this.leading,
    this.fullWidth = true,
    this.color,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final Widget? leading;
  final bool fullWidth;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final bg = color ?? AppColors.seed;

    return SizedBox(
      width: fullWidth ? double.infinity : null,
      height: TouchTargets.primaryAction,
      child: ElevatedButton(
        onPressed: busy ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: bg,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Radii.m)),
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: Insets.l),
        ),
        child: busy
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  valueColor: AlwaysStoppedAnimation(Colors.white),
                ),
              )
            : Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (leading != null) ...[leading!, const SizedBox(width: Insets.s)],
                  Text(
                    label,
                    style: AppType.titleM.copyWith(color: Colors.white, fontSize: 16),
                  ),
                ],
              ),
      ),
    );
  }
}
