// Economy.js
// Pure production math for the assembly-line / bottleneck model, cost growth,
// max-affordable helpers, bottleneck detection, and number formatting.
//
// All methods read from a plain `state` object with the shape:
//   state.ingredients[id] -> level (int, 0+)
//   state.gadgets[id]     -> owned/level (int, 0+)
// plus derived modifiers read from gadget levels.

import { ingredients, } from '../data/ingredients.js';
import { gadgets, gadgetById } from '../data/gadgets.js';

const GROWTH = 1.15;
const BASE_TAP = 1;

// Recipe consumption ratios (per khinkali).
const RECIPE = { flour: 1.0, meat: 1.0, herbs: 0.5 };

// Station id -> gadget id mapping for the 5-step chain.
const STATION_GADGET = {
  dough: 'doughMixer',
  meat: 'meatGrinder',
  herb: 'herbChopper',
  pleat: 'pleatingRobot',
  boil: 'steamCauldron',
};

export class Economy {
  constructor() {
    this.ingredients = ingredients;
    this.gadgets = gadgets;
  }

  // ---- level / owned accessors -------------------------------------------
  ingredientLevel(state, id) {
    return state.ingredients[id] || 0;
  }
  gadgetLevel(state, id) {
    return state.gadgets[id] || 0;
  }

  // ---- supply ------------------------------------------------------------
  /** Supply rate (units/sec) for one ingredient. level 0 already supplies base. */
  supplyRate(state, id) {
    const def = this.ingredients.find((i) => i.id === id);
    if (!def) return 0;
    const level = this.ingredientLevel(state, id);
    return def.baseSupply * (1 + level);
  }

  /** khinkali/sec achievable from current ingredient supply (recipe-gated). */
  ingredientRate(state) {
    const flour = this.supplyRate(state, 'flour') / RECIPE.flour;
    const meat = this.supplyRate(state, 'meat') / RECIPE.meat;
    const herbs = this.supplyRate(state, 'herbs') / RECIPE.herbs;
    return Math.min(flour, meat, herbs);
  }

  // ---- stations ----------------------------------------------------------
  /** Raw throughput of a single station (khinkali/sec) before global mods. */
  stationRate(state, station) {
    const gid = STATION_GADGET[station];
    const def = gadgetById[gid];
    if (!def) return 0;
    return def.baseRate * this.gadgetLevel(state, gid);
  }

  /** Bottleneck across the 5 machine stations. */
  machineRate(state) {
    return Math.min(
      this.stationRate(state, 'dough'),
      this.stationRate(state, 'meat'),
      this.stationRate(state, 'herb'),
      this.stationRate(state, 'pleat'),
      this.stationRate(state, 'boil')
    );
  }

  // ---- modifiers ---------------------------------------------------------
  /** Tier-3 multiplier: 1 + Σ(level * mult). */
  tier3Multiplier(state) {
    let sum = 0;
    for (const g of this.gadgets) {
      if (g.kind === 'tier3') sum += this.gadgetLevel(state, g.id) * g.mult;
    }
    return 1 + sum;
  }

  globalMultiplier(state) {
    const conveyor = this.gadgetLevel(state, 'conveyor');
    return (1 + 0.05 * conveyor) * this.tier3Multiplier(state);
  }

  goldPerKhinkali(state) {
    const seller = this.gadgetLevel(state, 'autoSeller');
    return 1 * (1 + 0.1 * seller);
  }

  // ---- rates -------------------------------------------------------------
  /** Automation-only khinkali per second (after global multiplier). */
  autoKPS(state) {
    return Math.min(this.ingredientRate(state), this.machineRate(state)) * this.globalMultiplier(state);
  }

  /** Whole-business gross gold/sec before owner share (used for valuation). */
  grossGoldPerSec(state) {
    return this.autoKPS(state) * this.goldPerKhinkali(state);
  }

  /** Passive gold/sec the OWNER receives (scaled by ownerShare). */
  passiveGoldPerSec(state, ownerShare) {
    return this.grossGoldPerSec(state) * ownerShare;
  }

  /** Gold earned per manual tap (NOT reduced by equity). */
  goldPerTap(state) {
    return BASE_TAP * this.goldPerKhinkali(state) * this.globalMultiplier(state);
  }

  // ---- cost growth -------------------------------------------------------
  cost(baseCost, level) {
    return Math.ceil(baseCost * Math.pow(GROWTH, level));
  }

  /** Total cost to buy `n` consecutive levels starting at `level`. */
  costForN(baseCost, level, n) {
    if (n <= 0) return 0;
    return Math.ceil((baseCost * Math.pow(GROWTH, level) * (Math.pow(GROWTH, n) - 1)) / (GROWTH - 1));
  }

  /** How many levels are affordable with `gold` starting at `level`. */
  maxAffordable(baseCost, level, gold) {
    if (gold < this.cost(baseCost, level)) return 0;
    // Closed-form inverse of costForN then refine to avoid float drift.
    const ratio = (gold * (GROWTH - 1)) / (baseCost * Math.pow(GROWTH, level)) + 1;
    let n = Math.floor(Math.log(ratio) / Math.log(GROWTH));
    if (n < 1) n = 1;
    // Refine downward if overshoot, upward if undershoot.
    while (n > 0 && this.costForN(baseCost, level, n) > gold) n--;
    while (this.costForN(baseCost, level, n + 1) <= gold) n++;
    return n;
  }

  /**
   * Resolve a purchase quantity for a buy mode.
   * mode: 1 | 10 | 'max'. Returns { count, totalCost }.
   */
  resolvePurchase(baseCost, level, gold, mode) {
    let count;
    if (mode === 'max') {
      count = this.maxAffordable(baseCost, level, gold);
    } else {
      count = mode;
    }
    const totalCost = this.costForN(baseCost, level, count);
    return { count, totalCost };
  }

  // ---- bottleneck detection ---------------------------------------------
  /**
   * Identify the single limiting step across ingredients and stations.
   * Returns { key, labelKey, fixGadgetId, fixType } or null when balanced.
   *   fixType: 'ingredient' | 'gadget'
   */
  detectBottleneck(state) {
    const terms = [
      { key: 'flour', labelKey: 'bn_flour', value: this.supplyRate(state, 'flour') / RECIPE.flour, fixType: 'ingredient', fixId: 'flour' },
      { key: 'meat', labelKey: 'bn_meat', value: this.supplyRate(state, 'meat') / RECIPE.meat, fixType: 'ingredient', fixId: 'meat' },
      { key: 'herbs', labelKey: 'bn_herbs', value: this.supplyRate(state, 'herbs') / RECIPE.herbs, fixType: 'ingredient', fixId: 'herbs' },
      { key: 'dough', labelKey: 'bn_dough', value: this.stationRate(state, 'dough'), fixType: 'gadget', fixId: 'doughMixer' },
      { key: 'meatstation', labelKey: 'bn_meatstation', value: this.stationRate(state, 'meat'), fixType: 'gadget', fixId: 'meatGrinder' },
      { key: 'herbstation', labelKey: 'bn_herbstation', value: this.stationRate(state, 'herb'), fixType: 'gadget', fixId: 'herbChopper' },
      { key: 'pleat', labelKey: 'bn_pleat', value: this.stationRate(state, 'pleat'), fixType: 'gadget', fixId: 'pleatingRobot' },
      { key: 'boil', labelKey: 'bn_boil', value: this.stationRate(state, 'boil'), fixType: 'gadget', fixId: 'steamCauldron' },
    ];
    // The lowest term is the bottleneck.
    let min = terms[0];
    for (const t of terms) if (t.value < min.value) min = t;
    return {
      key: min.key,
      labelKey: min.labelKey,
      fixType: min.fixType,
      fixId: min.fixId,
      value: min.value,
    };
  }

  // ---- number formatting -------------------------------------------------
  /**
   * Format a number for display.
   * < 1e6 : grouped integer (locale-aware).
   * >= 1e6: suffixes K, M, B, T then two-letter aa, ab, ac ...
   */
  format(n, locale) {
    if (!isFinite(n)) return '∞';
    const sign = n < 0 ? '-' : '';
    n = Math.abs(n);

    if (n < 1e6) {
      // Grouped integer below a million.
      const rounded = n < 1000 ? Math.floor(n) : Math.round(n);
      try {
        return sign + rounded.toLocaleString(locale || undefined);
      } catch (e) {
        return sign + rounded.toString();
      }
    }

    // Determine the "thousands power" (1 = K, 2 = M, ...).
    const power = Math.floor(Math.log10(n) / 3);
    const named = ['', 'K', 'M', 'B', 'T'];
    let suffix;
    if (power < named.length) {
      suffix = named[power];
    } else {
      // Two-letter scientific suffix after T: aa, ab, ac, ...
      const idx = power - named.length; // 0 -> aa
      const first = Math.floor(idx / 26);
      const second = idx % 26;
      suffix = String.fromCharCode(97 + first) + String.fromCharCode(97 + second);
    }
    const scaled = n / Math.pow(10, power * 3);
    // 2–3 significant decimals depending on magnitude of the mantissa.
    let str;
    if (scaled >= 100) str = scaled.toFixed(1);
    else if (scaled >= 10) str = scaled.toFixed(2);
    else str = scaled.toFixed(3);
    // Trim trailing zeros but keep at least one decimal for readability.
    str = str.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
    return sign + str + suffix;
  }

  /** Compact rate formatting for "/sec" labels. */
  formatRate(n, locale) {
    if (n < 1000 && n > 0 && n < 100) {
      return (Math.round(n * 100) / 100).toString();
    }
    return this.format(n, locale);
  }
}

export const economy = new Economy();
export default economy;
