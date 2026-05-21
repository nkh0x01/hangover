import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/motion.dart';
import '../theme/typography.dart';
import 'brand_logo.dart';

/// Full-bleed splash. Renders a gradient backdrop, animated brand mark
/// and a bilingual tagline. The actual auth-state decision happens in
/// the existing `SplashPage` controller; this widget is purely
/// presentational so apps can wrap it however they want.
class SplashContent extends StatefulWidget {
  const SplashContent({
    super.key,
    this.tagline = 'Tbilisi-ში სკუტერი ერთი დაკაკუნებით',
    this.taglineEn = 'Tbilisi on two wheels.',
  });

  final String tagline;
  final String taglineEn;

  @override
  State<SplashContent> createState() => _SplashContentState();
}

class _SplashContentState extends State<SplashContent> with SingleTickerProviderStateMixin {
  late final AnimationController _ctl;
  late final Animation<double> _entry;

  @override
  void initState() {
    super.initState();
    _ctl = AnimationController(vsync: this, duration: AppMotion.slow)..forward();
    _entry = CurvedAnimation(parent: _ctl, curve: AppCurves.pop);
  }

  @override
  void dispose() {
    _ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(gradient: AppGradients.primary),
      child: Stack(
        children: [
          // Decorative scooter wheels.
          Positioned(top: -80, left: -80, child: _Ring(size: 260, op: 0.08)),
          Positioned(bottom: -120, right: -80, child: _Ring(size: 320, op: 0.06)),
          Positioned(top: 220, right: 20, child: _Ring(size: 100, op: 0.04)),

          Center(
            child: AnimatedBuilder(
              animation: _entry,
              builder: (_, __) {
                final t = _entry.value;
                return Opacity(
                  opacity: t,
                  child: Transform.translate(
                    offset: Offset(0, 30 - 30 * t),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 96,
                          height: 96,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(
                                color: Colors.white.withValues(alpha: 0.4),
                                blurRadius: 40,
                              ),
                            ],
                          ),
                          alignment: Alignment.center,
                          child: const BrandLogo(
                            size: BrandLogoSize.hero,
                            showWordmark: false,
                          ),
                        ),
                        const SizedBox(height: Insets.xl),
                        Text(
                          'Hangover Mobility',
                          style: AppType.headlineL.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: Insets.xs),
                        Text(
                          widget.tagline,
                          textAlign: TextAlign.center,
                          style: AppType.body.copyWith(color: Colors.white.withValues(alpha: 0.9)),
                        ),
                        const SizedBox(height: Insets.xxs),
                        Text(
                          widget.taglineEn,
                          textAlign: TextAlign.center,
                          style: AppType.body.copyWith(color: Colors.white.withValues(alpha: 0.7)),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          Positioned(
            left: 0,
            right: 0,
            bottom: Insets.xxl,
            child: Center(
              child: Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                child: const CircularProgressIndicator(
                  strokeWidth: 2.4,
                  valueColor: AlwaysStoppedAnimation(Colors.white),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Ring extends StatelessWidget {
  const _Ring({required this.size, required this.op});
  final double size;
  final double op;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white.withValues(alpha: op * 3), width: 3),
        gradient: RadialGradient(
          colors: [
            Colors.white.withValues(alpha: op),
            Colors.white.withValues(alpha: 0),
          ],
        ),
      ),
    );
  }
}
