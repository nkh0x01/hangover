import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Success surface — used after ride completion, payout confirmation,
/// promo redemption etc.
class SuccessState extends StatelessWidget {
  const SuccessState({
    super.key,
    required this.headline,
    this.body,
    this.amount,
    this.currency,
  });

  final String headline;
  final String? body;
  final double? amount;
  final String? currency;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Insets.xl, horizontal: Insets.l),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 72,
            height: 72,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: Color(0xFFDCEFE3),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.check_rounded, color: AppColors.success, size: 40),
          ),
          const SizedBox(height: Insets.m),
          Text(headline, style: AppType.titleL, textAlign: TextAlign.center),
          if (body != null) ...[
            const SizedBox(height: Insets.xs),
            Text(body!, style: AppType.body, textAlign: TextAlign.center),
          ],
          if (amount != null) ...[
            const SizedBox(height: Insets.m),
            RichText(
              text: TextSpan(
                style: AppType.display.copyWith(fontSize: 36),
                children: [
                  TextSpan(text: amount!.toStringAsFixed(2)),
                  TextSpan(
                    text: ' ${currency ?? 'GEL'}',
                    style: AppType.titleL.copyWith(color: AppColors.inkMuted),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
