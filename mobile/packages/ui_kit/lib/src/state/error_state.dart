import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';
import '../widgets/primary_button.dart';

/// Standardised error state with retry. Pass the human message produced
/// by the `ApiError.message` decoder from the `network` package.
class ErrorStateView extends StatelessWidget {
  const ErrorStateView({
    super.key,
    required this.message,
    this.headline = 'Something went wrong',
    this.onRetry,
    this.retryLabel = 'Try again',
  });

  final String message;
  final String headline;
  final VoidCallback? onRetry;
  final String retryLabel;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Insets.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            width: 72,
            height: 72,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: const Color(0xFFFCE6E9),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.error_outline, color: AppColors.danger, size: 32),
          ),
          const SizedBox(height: Insets.l),
          Text(headline, style: AppType.titleL, textAlign: TextAlign.center),
          const SizedBox(height: Insets.s),
          Text(message, style: AppType.body, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: Insets.l),
            PrimaryButton(label: retryLabel, onPressed: onRetry),
          ],
        ],
      ),
    );
  }
}
