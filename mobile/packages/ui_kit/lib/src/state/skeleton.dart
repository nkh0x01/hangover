import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';

/// Shimmer loader used by lists and cards while content is loading.
class Skeleton extends StatefulWidget {
  const Skeleton({
    super.key,
    this.width,
    this.height = 16,
    this.radius = Radii.s,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _ctl =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat();

  @override
  void dispose() {
    _ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.width,
      height: widget.height,
      child: AnimatedBuilder(
        animation: _ctl,
        builder: (_, __) {
          final t = _ctl.value;
          return DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(widget.radius),
              gradient: LinearGradient(
                begin: Alignment(-1 + t * 2, 0),
                end: Alignment(1 + t * 2, 0),
                colors: const [
                  AppColors.outlineSubtle,
                  Color(0xFFE0DCC9),
                  AppColors.outlineSubtle,
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Convenience: a ride-history row skeleton.
class RideRowSkeleton extends StatelessWidget {
  const RideRowSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Insets.s),
      child: Row(
        children: const [
          Skeleton(width: 36, height: 36, radius: 18),
          SizedBox(width: Insets.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Skeleton(width: 180, height: 14),
                SizedBox(height: 6),
                Skeleton(width: 120, height: 12),
              ],
            ),
          ),
          Skeleton(width: 60, height: 14),
        ],
      ),
    );
  }
}
