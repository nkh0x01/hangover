// GameManager.js
// Central game state, production tick, purchases, save/load and offline income.
// Composes Economy + SharesSystem + SaveSystem.

import { economy } from './Economy.js';
import { SharesSystem } from './SharesSystem.js';
import { saveSystem } from './SaveSystem.js';
import { ingredients } from '../data/ingredients.js';
import { gadgets, gadgetById } from '../data/gadgets.js';

const AUTOSAVE_INTERVAL = 10; // seconds
const OFFLINE_CAP_SEC = 8 * 3600; // 8 hours

export class GameManager {
  constructor() {
    this.economy = economy;
    this.shares = new SharesSystem(economy);
    this.save = saveSystem;
    this.state = this.defaultState();
    this._sinceSave = 0;
    // Runtime-only cosmetic ingredient buffers (0..1) for the fill indicators.
    this.buffers = { flour: 0.5, meat: 0.5, herbs: 0.5 };
    // Cached derived values, refreshed each tick for cheap UI reads.
    this.cache = { autoKPS: 0, passive: 0, goldPerTap: 1, valuation: 0, ownerShare: 1 };
    this.offlineReport = null;
  }

  defaultState() {
    const gadgetState = {};
    gadgets.forEach((g) => (gadgetState[g.id] = 0));
    const ingState = {};
    ingredients.forEach((i) => (ingState[i.id] = 0));
    return {
      gold: 0,
      totalGoldEarned: 0,
      totalBaked: 0,
      ingredients: ingState,
      gadgets: gadgetState,
      shares: { equitySold: 0, cooldowns: {} },
      buyMode: 1,
      lastSave: Date.now(),
    };
  }

  // ---- init / load -------------------------------------------------------
  init() {
    const loaded = this.save.load();
    if (loaded) this._applyLoaded(loaded);
    const now = Date.now();
    this.shares.clampCooldowns(this.state, now);
    this._computeOffline(now);
    this.state.lastSave = now;
    this.refresh();
    return this;
  }

  _applyLoaded(loaded) {
    const base = this.defaultState();
    // Merge defensively so new fields / gadgets always exist.
    this.state = {
      ...base,
      ...loaded,
      ingredients: { ...base.ingredients, ...(loaded.ingredients || {}) },
      gadgets: { ...base.gadgets, ...(loaded.gadgets || {}) },
      shares: {
        equitySold: (loaded.shares && loaded.shares.equitySold) || 0,
        cooldowns: (loaded.shares && loaded.shares.cooldowns) || {},
      },
    };
    // Clamp equity within [0, cap].
    this.state.shares.equitySold = Math.max(0, Math.min(0.8, this.state.shares.equitySold));
  }

  _computeOffline(now) {
    const elapsed = Math.max(0, (now - (this.state.lastSave || now)) / 1000);
    const capped = Math.min(elapsed, OFFLINE_CAP_SEC);
    const passive = this.economy.passiveGoldPerSec(this.state, this.shares.ownerShare(this.state));
    const earned = passive * capped;
    if (elapsed > 60 && earned > 0) {
      this.state.gold += earned;
      this.state.totalGoldEarned += earned;
      this.offlineReport = { earned, seconds: capped };
    } else {
      this.offlineReport = null;
    }
  }

  // ---- per-frame ---------------------------------------------------------
  tick(dt) {
    const passive = this.cache.passive;
    if (passive > 0) {
      const gain = passive * dt;
      this.state.gold += gain;
      this.state.totalGoldEarned += gain;
      // Passive production also counts toward total baked.
      this.state.totalBaked += this.cache.autoKPS * dt;
    }

    // Cosmetic ingredient buffers: fill toward 1 at a rate tied to supply,
    // gently drained by consumption so they visibly "breathe" while producing.
    const consume = this.cache.autoKPS;
    for (const ing of ingredients) {
      const supply = this.economy.supplyRate(this.state, ing.id);
      const fillRate = supply > 0 ? 0.35 : 0;
      const drain = (consume * ing.perKhinkali) / Math.max(1, supply) * 0.3;
      let b = this.buffers[ing.id] + (fillRate - drain) * dt;
      this.buffers[ing.id] = Math.max(0.05, Math.min(1, b));
    }

    // Autosave on interval.
    this._sinceSave += dt;
    if (this._sinceSave >= AUTOSAVE_INTERVAL) {
      this._sinceSave = 0;
      this.persist();
    }
  }

  /** Recompute cached derived values (call after any state change). */
  refresh() {
    const s = this.state;
    const ownerShare = this.shares.ownerShare(s);
    this.cache.autoKPS = this.economy.autoKPS(s);
    this.cache.ownerShare = ownerShare;
    this.cache.passive = this.economy.passiveGoldPerSec(s, ownerShare);
    this.cache.goldPerTap = this.economy.goldPerTap(s);
    this.cache.valuation = this.shares.valuation(s);
  }

  // ---- manual tap --------------------------------------------------------
  /**
   * Manual assemble tap. Returns { gold, khinkali, golden } where golden marks
   * a rare 7x "golden khinkali".
   */
  manualTap() {
    let perTap = this.cache.goldPerTap;
    let golden = false;
    // Rare golden khinkali: ~2% chance, worth 7x.
    if (Math.random() < 0.02) {
      perTap *= 7;
      golden = true;
    }
    this.state.gold += perTap;
    this.state.totalGoldEarned += perTap;
    this.state.totalBaked += 1;
    return { gold: perTap, khinkali: 1, golden };
  }

  /** Tap an ingredient slot — tiny instant cosmetic buffer bonus. */
  tapIngredient(id) {
    if (this.buffers[id] === undefined) return;
    this.buffers[id] = Math.min(1, this.buffers[id] + 0.18);
  }

  // ---- purchases ---------------------------------------------------------
  ingredientDef(id) {
    return ingredients.find((i) => i.id === id);
  }

  buyIngredient(id, mode) {
    const def = this.ingredientDef(id);
    if (!def) return { ok: false };
    const level = this.state.ingredients[id] || 0;
    const { count, totalCost } = this.economy.resolvePurchase(def.baseCost, level, this.state.gold, mode);
    if (count <= 0 || totalCost > this.state.gold) return { ok: false, reason: 'cannot_afford' };
    this.state.gold -= totalCost;
    this.state.ingredients[id] = level + count;
    this.refresh();
    return { ok: true, count, cost: totalCost };
  }

  buyGadget(id, mode) {
    const def = gadgetById[id];
    if (!def) return { ok: false };
    const level = this.state.gadgets[id] || 0;
    const { count, totalCost } = this.economy.resolvePurchase(def.baseCost, level, this.state.gold, mode);
    if (count <= 0 || totalCost > this.state.gold) return { ok: false, reason: 'cannot_afford' };
    this.state.gold -= totalCost;
    this.state.gadgets[id] = level + count;
    this.refresh();
    return { ok: true, count, cost: totalCost };
  }

  // ---- shares ------------------------------------------------------------
  raise(investor) {
    const res = this.shares.raise(this.state, investor, Date.now());
    if (res.ok) this.refresh();
    return res;
  }

  buyback() {
    const res = this.shares.buyback(this.state);
    if (res.ok) this.refresh();
    return res;
  }

  // ---- persistence -------------------------------------------------------
  persist() {
    this.state.lastSave = Date.now();
    this.save.save(this.state);
  }

  reset() {
    this.save.clear();
    this.state = this.defaultState();
    this.buffers = { flour: 0.5, meat: 0.5, herbs: 0.5 };
    this.offlineReport = null;
    this.refresh();
  }

  // ---- convenience getters ----------------------------------------------
  get gold() {
    return this.state.gold;
  }
  get totalBaked() {
    return this.state.totalBaked;
  }
  getBottleneck() {
    return this.economy.detectBottleneck(this.state);
  }
  /** Production intensity 0..1 for steam/glow (log scale). */
  getIntensity() {
    const k = this.cache.autoKPS;
    if (k <= 0) return 0;
    // Map ~0.5 KPS -> low, ~5000 KPS -> full, logarithmically.
    return Math.max(0, Math.min(1, Math.log10(k + 1) / 4));
  }
}

export default GameManager;
