/**
 * Game data: tap-power upgrades and KPS generators.
 * `nameKey` references i18n keys in lang.json; `icon` is an emoji for the UI.
 * All numeric values are the canonical balance numbers for Khinkali Empire.
 */

// Tap-power upgrades: each owned level adds a flat bonus to gold-per-tap.
// Cost scales with the standard 1.15^level rule on the upgrade's own level.
export const tapUpgrades = [
  { id: 'strongHands', nameKey: 'u_strong_hands', icon: '✊', baseCost: 50, tapBonus: 1, maxLevel: 200 },
  { id: 'sharperKnife', nameKey: 'u_sharper_knife', icon: '🔪', baseCost: 2500, tapBonus: 10, maxLevel: 200 },
];

// Generators: each owned unit adds `baseKps`; cost = baseCost * 1.15^owned.
export const generators = [
  { id: 'grandma', nameKey: 'u_grandma', icon: '👵', baseCost: 15, baseKps: 0.1 },
  { id: 'cook', nameKey: 'u_cook', icon: '👨‍🍳', baseCost: 100, baseKps: 1 },
  { id: 'cauldron', nameKey: 'u_cauldron', icon: '🍲', baseCost: 1100, baseKps: 8 },
  { id: 'khinkaliHouse', nameKey: 'u_khinkali_house', icon: '🏠', baseCost: 12000, baseKps: 47 },
  { id: 'restaurant', nameKey: 'u_restaurant', icon: '🍴', baseCost: 130000, baseKps: 260 },
  { id: 'factory', nameKey: 'u_factory', icon: '🏭', baseCost: 1400000, baseKps: 1400 },
  { id: 'village', nameKey: 'u_village', icon: '🏘️', baseCost: 20000000, baseKps: 7800 },
  { id: 'tower', nameKey: 'u_tower', icon: '🗼', baseCost: 330000000, baseKps: 44000 },
  { id: 'city', nameKey: 'u_city', icon: '🏙️', baseCost: 5100000000, baseKps: 260000 },
  { id: 'empire', nameKey: 'u_empire', icon: '👑', baseCost: 75000000000, baseKps: 1600000 },
];

// Cost-growth factor shared by every purchasable thing.
export const GROWTH = 1.15;
