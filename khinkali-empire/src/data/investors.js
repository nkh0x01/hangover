// Investor terms for the shares / financing meta system.
// equityPerRound: equity sold per round (fraction of 1.0)
// cashMultiple:   multiplier applied to (valuation * equityPerRound)
// cooldownSec:    seconds before this investor can be tapped again
// minValuation:   business valuation required before this investor unlocks
export const investors = [
  { id: 'tbilisiAngels', nameKey: 'inv_tbilisi_angels', minValuation: 0, equityPerRound: 0.05, cashMultiple: 0.9, cooldownSec: 90 },
  { id: 'mountainVentures', nameKey: 'inv_mountain_ventures', minValuation: 1000000, equityPerRound: 0.1, cashMultiple: 1.1, cooldownSec: 150 },
  { id: 'silkRoadCapital', nameKey: 'inv_silkroad', minValuation: 1000000000, equityPerRound: 0.15, cashMultiple: 1.3, cooldownSec: 240 },
];

export default investors;
