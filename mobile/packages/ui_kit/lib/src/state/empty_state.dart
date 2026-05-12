import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';
import '../widgets/primary_button.dart';

/// Standardised empty state. Use when a list/board has zero items —
/// "no rides yet", "no nearby drivers", etc. Action is optional.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.headline,
    this.body,
    this.icon = Icons.inbox_outlined,
    this.actionLabel,
    this.onAction,
  });

  final String headline;
  final String? body;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Insets.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: AppColors.surfaceVariant,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: AppColors.inkSoft, size: 32),
          ),
          const SizedBox(height: Insets.l),
          Text(headline, style: AppType.titleL, textAlign: TextAlign.center),
          if (body != null) ...[
            const SizedBox(height: Insets.s),
            Text(body!, style: AppType.body, textAlign: TextAlign.center),
          ],
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: Insets.l),
            PrimaryButton(label: actionLabel!, onPressed: onAction, fullWidth: false),
          ],
        ],
      ),
    );
  }
}
