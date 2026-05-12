import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';

/// Consistent rounded-top card the customer + driver apps lift over the
/// map for ride status, fare estimate, incoming offer, etc. Phase 1.6
/// pulls these into one widget so the corner radius, padding, drop
/// shadow and a small drag handle render identically across screens.
class BottomSheetCard extends StatelessWidget {
  const BottomSheetCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(Insets.l),
    this.handle = true,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final bool handle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(Radii.xl)),
        boxShadow: const [
          BoxShadow(
            blurRadius: 20,
            offset: Offset(0, -4),
            color: Color(0x1F000000),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: padding,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (handle)
                Center(
                  child: Container(
                    width: 36,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: Insets.m),
                    decoration: BoxDecoration(
                      color: AppColors.outline,
                      borderRadius: BorderRadius.circular(Radii.pill),
                    ),
                  ),
                ),
              child,
            ],
          ),
        ),
      ),
    );
  }
}
