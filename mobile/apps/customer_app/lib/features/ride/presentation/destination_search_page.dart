import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:ui_kit/ui_kit.dart';

import '../state/ride_flow_controller.dart';

/// Phase 1.6 v2 — premium destination picker.
///
/// Backed by [MapProvider.placeAutocomplete] (Phase 2 fills the real
/// Google Places integration; for now we render a friendly empty/
/// recent-history view). Visually this is the search affordance the
/// brief asked for; the actual tap-to-pick map fallback stays under
/// `destination_page.dart` for now.
class DestinationSearchPage extends ConsumerStatefulWidget {
  const DestinationSearchPage({super.key});

  @override
  ConsumerState<DestinationSearchPage> createState() => _DestinationSearchPageState();
}

class _DestinationSearchPageState extends ConsumerState<DestinationSearchPage> {
  final _controller = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final results = _query.isEmpty ? const <_PlaceRow>[] : _matches(_query);

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Column(
          children: [
            // ---- Search header ----------------------------------------
            Padding(
              padding: const EdgeInsets.fromLTRB(Insets.l, Insets.m, Insets.l, Insets.s),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => Navigator.of(context).pop(),
                    child: Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                        boxShadow: AppShadows.fab,
                      ),
                      child: const Icon(Icons.arrow_back_rounded, color: AppColors.ink, size: 20),
                    ),
                  ),
                  const SizedBox(width: Insets.m),
                  Expanded(
                    child: Container(
                      height: 52,
                      padding: const EdgeInsets.symmetric(horizontal: Insets.l),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(Radii.l),
                        boxShadow: AppShadows.card,
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.search_rounded, color: AppColors.inkSoft),
                          const SizedBox(width: Insets.s),
                          Expanded(
                            child: TextField(
                              controller: _controller,
                              autofocus: true,
                              onChanged: (v) => setState(() => _query = v),
                              decoration: const InputDecoration(
                                hintText: 'სად მიდიხართ? · Where to?',
                                border: InputBorder.none,
                                isCollapsed: true,
                                filled: false,
                              ),
                              style: AppType.titleM,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ---- Saved places strip ----------------------------------
            if (_query.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: Insets.l, vertical: Insets.s),
                child: Row(
                  children: const [
                    _Saved(icon: Icons.home_rounded, label: 'Home', sub: 'Saburtalo'),
                    SizedBox(width: Insets.s),
                    _Saved(icon: Icons.work_rounded, label: 'Work', sub: 'Vake'),
                    SizedBox(width: Insets.s),
                    _Saved(icon: Icons.add_rounded, label: 'Add', sub: '', muted: true),
                  ],
                ),
              ),

            // ---- Results list ----------------------------------------
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: Insets.l),
                children: [
                  if (_query.isEmpty) ...[
                    const SizedBox(height: Insets.l),
                    Text('Recent', style: Theme.of(context).textTheme.labelMedium),
                    const _PlaceTile(
                      icon: Icons.history_rounded,
                      title: 'Vake Park',
                      subtitle: '3 days ago · 7.5 GEL',
                    ),
                    const _PlaceTile(
                      icon: Icons.history_rounded,
                      title: 'Rustaveli Theatre',
                      subtitle: '1 week ago · 6.2 GEL',
                    ),
                    const _PlaceTile(
                      icon: Icons.history_rounded,
                      title: 'Tbilisi Mall',
                      subtitle: '2 weeks ago · 12.2 GEL',
                    ),
                  ] else ...[
                    for (final r in results)
                      _PlaceTile(
                        icon: Icons.location_on_rounded,
                        title: r.title,
                        subtitle: r.subtitle,
                        accent: true,
                        onTap: () {
                          ref.read(rideFlowProvider.notifier)
                              .setDropoff(r.point, address: r.title);
                          context.replace('/ride/estimate');
                        },
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<_PlaceRow> _matches(String q) {
    final all = [
      _PlaceRow('Vake Park', 'Vake district · Tbilisi', const LatLng(41.7110, 44.7728)),
      _PlaceRow('Tbilisi Mall', 'Didi Dighomi · Tbilisi', const LatLng(41.7783, 44.7635)),
      _PlaceRow('Rustaveli Theatre', 'Rustaveli Ave 17', const LatLng(41.7000, 44.7956)),
      _PlaceRow('Mtatsminda Park', 'Mt Mtatsminda · Tbilisi', const LatLng(41.6961, 44.7892)),
      _PlaceRow('Marjanishvili 4', 'Marjanishvili district', const LatLng(41.7253, 44.7977)),
      _PlaceRow('Lisi Lake', 'Saburtalo · Tbilisi', const LatLng(41.7506, 44.7172)),
      _PlaceRow('Old Tbilisi', 'Freedom Square area', const LatLng(41.6936, 44.8014)),
    ];
    return all.where((r) => r.title.toLowerCase().contains(q.toLowerCase())).toList();
  }
}

class _PlaceRow {
  _PlaceRow(this.title, this.subtitle, this.point);
  final String title;
  final String subtitle;
  final LatLng point;
}

class _Saved extends StatelessWidget {
  const _Saved({required this.icon, required this.label, required this.sub, this.muted = false});
  final IconData icon;
  final String label;
  final String sub;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(Insets.m),
        decoration: BoxDecoration(
          color: muted ? AppColors.surfaceVariant : Colors.white,
          borderRadius: BorderRadius.circular(Radii.l),
          boxShadow: muted ? null : AppShadows.card,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: muted ? AppColors.outlineSubtle : AppColors.surfaceVariant,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Icon(icon, size: 16, color: AppColors.ink),
            ),
            const SizedBox(height: Insets.s),
            Text(label, style: AppType.bodyStrong),
            if (sub.isNotEmpty) Text(sub, style: AppType.caption),
          ],
        ),
      ),
    );
  }
}

class _PlaceTile extends StatelessWidget {
  const _PlaceTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.accent = false,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool accent;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Radii.l),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: Insets.m),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: accent ? const Color(0x1A1F8F60) : AppColors.surfaceVariant,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Icon(icon, color: accent ? AppColors.seed : AppColors.inkSoft, size: 18),
            ),
            const SizedBox(width: Insets.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppType.titleM),
                  const SizedBox(height: 2),
                  Text(subtitle, style: AppType.caption),
                ],
              ),
            ),
            const Icon(Icons.north_west_rounded, size: 14, color: AppColors.inkMuted),
          ],
        ),
      ),
    );
  }
}
