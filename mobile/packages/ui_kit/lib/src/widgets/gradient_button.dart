import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Premium hero CTA. Uses [AppGradients.primary] (or [AppGradients.accent]
/// for the variant) with a soft tinted shadow so it visually lifts off
/// the surface. 60-pixel tap target — same as [PrimaryButton] — but
/// reserved for hero moments (splash "Get started", final "Request
/// ride", driver "Accept ride").
class GradientButton extends StatelessWidget {
  const GradientButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.busy = false,
    this.leading,
    this.trailing,
    this.gradient,
    this.shadows,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final Widget? leading;
  final Widget? trailing;
  final Gradient? gradient;
  final List<BoxShadow>? shadows;

  @override
  Widget build(BuildContext context) {
    final disabled = busy || onPressed == null;
    return Opacity(
      opacity: disabled ? 0.55 : 1,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(Radii.m),
          onTap: disabled ? null : onPressed,
          child: Ink(
            height: TouchTargets.primaryAction,
            decoration: BoxDecoration(
              gradient: gradient ?? AppGradients.primary,
              borderRadius: BorderRadius.circular(Radii.m),
              boxShadow: shadows ?? AppShadows.heroGreen,
            ),
            child: Center(
              child: busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.4,
                        valueColor: AlwaysStoppedAnimation(Colors.white),
                      ),
                    )
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (leading != null) ...[leading!, const SizedBox(width: Insets.s)],
                        Text(
                          label,
                          style: AppType.titleM.copyWith(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                        ),
                        if (trailing != null) ...[const SizedBox(width: Insets.s), trailing!],
                      ],
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
