import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../shift/state/shift_controller.dart';

/// Full-screen modal that takes over the driver app when an offer comes
/// in. A live countdown reflects the server's expires_at; if it ticks
/// to zero locally we treat that as a reject and the server will
/// also time it out independently.
class IncomingOfferSheet extends ConsumerStatefulWidget {
  const IncomingOfferSheet({super.key, required this.offer});

  final RideOfferPayload offer;

  @override
  ConsumerState<IncomingOfferSheet> createState() => _IncomingOfferSheetState();
}

class _IncomingOfferSheetState extends ConsumerState<IncomingOfferSheet> {
  Timer? _ticker;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _remaining = widget.offer.expiresAt.difference(DateTime.now());
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      final r = widget.offer.expiresAt.difference(DateTime.now());
      if (r.isNegative) {
        _ticker?.cancel();
        ref.read(shiftProvider.notifier).rejectOffer(widget.offer.rideId);
        return;
      }
      setState(() => _remaining = r);
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    return Container(
      color: Colors.black54,
      alignment: Alignment.bottomCenter,
      child: Container(
        margin: const EdgeInsets.all(Insets.l),
        padding: const EdgeInsets.all(Insets.xl),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(Radii.l),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('New ride', style: Theme.of(context).textTheme.titleLarge),
                Text('${_remaining.inSeconds}s', style: Theme.of(context).textTheme.titleMedium),
              ],
            ),
            const SizedBox(height: Insets.m),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.my_location),
              title: Text(offer.pickupAddress),
              subtitle: Text('~${offer.distanceToPickupM} m to pickup'),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.flag),
              title: Text(offer.dropoffAddress),
            ),
            const SizedBox(height: Insets.s),
            Text(
              'Fare: ${offer.fareAmount.toStringAsFixed(2)} ${offer.currency}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: Insets.l),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () =>
                        ref.read(shiftProvider.notifier).rejectOffer(offer.rideId),
                    child: const Text('Reject'),
                  ),
                ),
                const SizedBox(width: Insets.m),
                Expanded(
                  child: PrimaryButton(
                    label: 'Accept',
                    onPressed: () => ref.read(shiftProvider.notifier).acceptOffer(offer.rideId),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
