import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Outlined "soft" CTA. Used for "Reject offer" on the driver side and
/// "Cancel ride" on the customer side. Lives next to a [PrimaryButton]
/// but is visibly secondary — outline + label-coloured, never filled.
class SecondaryButton extends StatelessWidget {
  const SecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.color,
    this.leading,
    this.fullWidth = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final Color? color;
  final Widget? leading;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? AppColors.inkSoft;
    return SizedBox(
      width: fullWidth ? double.infinity : null,
      height: TouchTargets.primaryAction,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: accent,
          side: BorderSide(color: accent.withValues(alpha: 0.4), width: 1.5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Radii.m)),
          textStyle: AppType.titleM.copyWith(color: accent),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: Insets.s)],
            Text(label, style: AppType.titleM.copyWith(color: accent, fontSize: 16)),
          ],
        ),
      ),
    );
  }
}
