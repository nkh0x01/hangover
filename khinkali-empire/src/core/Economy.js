/**
 * Economy
 * -------
 * Pure math for costs, KPS, gold-per-tap, and big-number formatting.
 * Stateless helpers — no game state lives here.
 */

import { GROWTH } from '../data/upgrades.js';

/** Cost of the NEXT single purchase given the current owned level. */
export function cost(baseCost, level) {
  return Math.ceil(baseCost * Math.pow(GROWTH, level));
}

/**
 * Geometric-series cost of buying `n` units starting from `level`.
 * Sum_{i=0..n-1} baseCost * r^(level+i) = baseCost * r^level * (r^n - 1)/(r - 1).
 */
export function costForN(baseCost, level, n) {
  if (n <= 0) return 0;
  const r = GROWTH;
  return Math.ceil(
    baseCost * Math.pow(r, level) * ((Math.pow(r, n) - 1) / (r - 1))
  );
}

/**
 * How many units can be bought with `gold` starting at `level`, optionally
 * capped at `maxN` (e.g. for x10 mode or a maxLevel limit). Returns 0..cap.
 */
export function maxAffordable(baseCost, level, gold, maxN = Infinity) {
  if (gold < cost(baseCost, level)) return 0;
  const r = GROWTH;
  // Solve baseCost * r^level * (r^n - 1)/(r-1) <= gold  for n.
  const ratio = (gold * (r - 1)) / (baseCost * Math.pow(r, level)) + 1;
  let n = Math.floor(Math.log(ratio) / Math.log(r));
  if (!isFinite(n) || n < 0) n = 0;
  n = Math.min(n, maxN);
  // Guard against floating-point overshoot/undershoot at the boundary.
  while (n > 0 && costForN(baseCost, level, n) > gold) n--;
  while (costForN(baseCost, level, n + 1) <= gold && n + 1 <= maxN) n++;
  return n;
}

/** Global production multiplier from prestige + any global upgrade bonuses. */
export function globalMultiplier(prestigePoints, extraMult = 0) {
  return 1 + 0.02 * prestigePoints + extraMult;
}

/** Total KPS from owned generators times the global multiplier. */
export function computeKPS(generators, owned, mult) {
  let sum = 0;
  for (const g of generators) {
    sum += (owned[g.id] || 0) * g.baseKps;
  }
  return sum * mult;
}

/** Gold gained per tap: (base + flat tap-upgrade bonuses) * global multiplier. */
export function computeTapPower(baseTap, tapUpgrades, levels, mult) {
  let bonus = 0;
  for (const u of tapUpgrades) {
    bonus += (levels[u.id] || 0) * u.tapBonus;
  }
  return (baseTap + bonus) * mult;
}

// ---------------------------------------------------------------------------
// Number formatting for huge idle numbers.
// Below 1e6: locale thousands separators.
// 1e6+: K, M, B, T, then two-letter suffixes aa, ab, ... az, ba, ...
// ---------------------------------------------------------------------------

const FIXED_SUFFIXES = ['', 'K', 'M', 'B', 'T'];

/** Build the suffix for a given group index (>=5 → two-letter aa, ab, ...). */
function suffixFor(groupIndex) {
  if (groupIndex < FIXED_SUFFIXES.length) return FIXED_SUFFIXES[groupIndex];
  // groupIndex 5 -> "aa". Each two-letter combo covers 26*26 magnitudes.
  let n = groupIndex - FIXED_SUFFIXES.length; // 0-based into aa, ab, ...
  const first = Math.floor(n / 26);
  const second = n % 26;
  return String.fromCharCode(97 + first) + String.fromCharCode(97 + second);
}

/**
 * Format a number for display.
 * @param {number} value
 * @param {string} locale - active language code for separators (optional).
 */
export function format(value, locale = 'en') {
  if (!isFinite(value)) return '∞';
  if (value < 0) return '-' + format(-value, locale);

  if (value < 1e6) {
    // Integers get grouping; small fractional gold shows up to 1 decimal.
    if (value < 1000 && value % 1 !== 0) {
      return new Intl.NumberFormat(localeTag(locale), {
        maximumFractionDigits: 1,
      }).format(value);
    }
    return new Intl.NumberFormat(localeTag(locale)).format(Math.floor(value));
  }

  // Determine the magnitude group (each group = 1000x).
  const groupIndex = Math.floor(Math.log10(value) / 3);
  const scaled = value / Math.pow(1000, groupIndex);
  const suffix = suffixFor(groupIndex);

  // 2–3 significant decimals: show more decimals for small mantissas.
  let decimals;
  if (scaled < 10) decimals = 2;
  else if (scaled < 100) decimals = 1;
  else decimals = 0;

  const mantissa = new Intl.NumberFormat(localeTag(locale), {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(scaled);

  return mantissa + suffix;
}

/** Map our language codes to BCP-47 tags for Intl. */
function localeTag(code) {
  switch (code) {
    case 'ka':
      return 'ka-GE';
    case 'ru':
      return 'ru-RU';
    case 'de':
      return 'de-DE';
    default:
      return 'en-US';
  }
}
