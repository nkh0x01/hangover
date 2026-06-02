/**
 * GameManager
 * -----------
 * The heart of the game: holds state, runs the economy tick (gold += kps*dt),
 * handles taps (incl. rare golden khinkali), purchases (x1/x10/max), prestige,
 * autosave, and offline earnings. Drives world FX in response to state changes.
 */

import { generators, tapUpgrades } from '../data/upgrades.js';
import {
  cost,
  costForN,
  maxAffordable,
  globalMultiplier,
  computeKPS,
  computeTapPower,
} from './Economy.js';
import { SaveSystem } from './SaveSystem.js';

const BASE_TAP = 1; // base gold per tap before upgrades
const PRESTIGE_UNLOCK = 1_000_000; // total gold earned needed to prestige
const AUTOSAVE_INTERVAL = 10; // seconds
const GOLDEN_CHANCE = 0.04; // chance a tap is a golden khinkali
const GOLDEN_MULT = 7; // golden tap reward multiplier

export class GameManager {
  /**
   * @param {object} world - { tower, smoke, particles, scene, sceneManager }
   */
  constructor(world) {
    this.world = world;
    this.state = SaveSystem.defaultState();
    this.generators = generators;
    this.tapUpgrades = tapUpgrades;

    // Derived/cached values, recomputed on change.
    this.kps = 0;
    this.tapPower = BASE_TAP;
    this._autosaveTimer = 0;

    // Listeners (UIManager subscribes to refresh HUD/shop).
    this._changeListeners = new Set();
  }

  /** Load save + offline earnings. Returns offline info for the welcome modal. */
  init() {
    const { state, offline } = SaveSystem.load((s) => {
      // KPS resolver used to compute offline earnings from the SAVED state.
      const mult = globalMultiplier(s.prestigePoints || 0);
      return computeKPS(generators, s.generators || {}, mult);
    });
    this.state = state;
    this.recompute();
    // Reflect loaded progress into the world immediately.
    this.world.tower.setProgress(this.state.totalGoldEarned);
    this.world.smoke.setIntensity(this.world.tower.intensity());
    this.world.sceneManager.setHearth(this.world.tower.intensity() * 1.2);
    return offline; // { seconds, gold }
  }

  onChange(fn) {
    this._changeListeners.add(fn);
    return () => this._changeListeners.delete(fn);
  }

  _emitChange() {
    this._changeListeners.forEach((fn) => fn());
  }

  /** Recompute KPS, tap power and global multiplier from current state. */
  recompute() {
    this.mult = globalMultiplier(this.state.prestigePoints);
    this.kps = computeKPS(generators, this.state.generators, this.mult);
    this.tapPower = computeTapPower(
      BASE_TAP,
      tapUpgrades,
      this.state.tapLevels,
      this.mult
    );
  }

  // ------------------------------------------------------------------
  // Per-frame update
  // ------------------------------------------------------------------
  update(dt) {
    // Passive income applied smoothly via delta time.
    if (this.kps > 0) {
      const earned = this.kps * dt;
      this.state.gold += earned;
      this.state.totalGoldEarned += earned;
    }

    // Evolution: re-check stage thresholds; bump smoke/hearth if we crossed one.
    if (this.world.tower.setProgress(this.state.totalGoldEarned)) {
      this.world.smoke.setIntensity(this.world.tower.intensity());
      this.world.sceneManager.setHearth(this.world.tower.intensity() * 1.2);
      this._emitChange();
    }

    // Autosave.
    this._autosaveTimer += dt;
    if (this._autosaveTimer >= AUTOSAVE_INTERVAL) {
      this._autosaveTimer = 0;
      this.save();
    }
  }

  // ------------------------------------------------------------------
  // Tapping
  // ------------------------------------------------------------------
  onTap(info) {
    const golden = Math.random() < GOLDEN_CHANCE;
    const gain = golden ? this.tapPower * GOLDEN_MULT : this.tapPower;
    this.state.gold += gain;
    this.state.totalGoldEarned += gain;

    // World juice.
    if (this.world.hero) {
      this.world.squishHero();
    }
    this.world.tower.recoil();
    if (info && info.point) {
      this.world.particles.spawnTapBurst(info.point, golden);
    }

    // Stage may cross a threshold from a big tap; re-check.
    if (this.world.tower.setProgress(this.state.totalGoldEarned)) {
      this.world.smoke.setIntensity(this.world.tower.intensity());
      this.world.sceneManager.setHearth(this.world.tower.intensity() * 1.2);
    }

    this._emitChange();
    return { gain, golden };
  }

  // ------------------------------------------------------------------
  // Purchases
  // ------------------------------------------------------------------

  /** Resolve how many units of mode ('1'|'10'|'max') a generator buy means. */
  _resolveCount(baseCost, level, mode, maxLevelRemaining = Infinity) {
    if (mode === 'max') {
      return maxAffordable(baseCost, level, this.state.gold, maxLevelRemaining);
    }
    const requested = mode === '10' ? 10 : 1;
    const n = Math.min(requested, maxLevelRemaining);
    // For x1/x10 we still require affordability of the FULL batch.
    return n;
  }

  buyGenerator(id, mode = '1') {
    const g = generators.find((x) => x.id === id);
    if (!g) return false;
    const owned = this.state.generators[id] || 0;
    let n = this._resolveCount(g.baseCost, owned, mode);
    if (n <= 0) return false;
    const price = costForN(g.baseCost, owned, n);
    if (price > this.state.gold) {
      // For x1/x10, if unaffordable, buy as many as possible (>=1) instead of 0.
      if (mode !== 'max') {
        n = maxAffordable(g.baseCost, owned, this.state.gold, n);
        if (n <= 0) return false;
      } else {
        return false;
      }
    }
    const finalPrice = costForN(g.baseCost, owned, n);
    if (finalPrice > this.state.gold) return false;
    this.state.gold -= finalPrice;
    this.state.generators[id] = owned + n;
    this.recompute();
    this.save();
    this._emitChange();
    return true;
  }

  buyTapUpgrade(id, mode = '1') {
    const u = tapUpgrades.find((x) => x.id === id);
    if (!u) return false;
    const level = this.state.tapLevels[id] || 0;
    const remaining = (u.maxLevel ?? Infinity) - level;
    if (remaining <= 0) return false;
    let n = this._resolveCount(u.baseCost, level, mode, remaining);
    if (n <= 0) return false;
    let price = costForN(u.baseCost, level, n);
    if (price > this.state.gold) {
      if (mode !== 'max') {
        n = maxAffordable(u.baseCost, level, this.state.gold, Math.min(n, remaining));
        if (n <= 0) return false;
      } else {
        return false;
      }
    }
    price = costForN(u.baseCost, level, n);
    if (price > this.state.gold) return false;
    this.state.gold -= price;
    this.state.tapLevels[id] = level + n;
    this.recompute();
    this.save();
    this._emitChange();
    return true;
  }

  // ------------------------------------------------------------------
  // Query helpers for the UI
  // ------------------------------------------------------------------
  getGold() {
    return this.state.gold;
  }
  getKPS() {
    return this.kps;
  }
  getTapPower() {
    return this.tapPower;
  }
  getTotalEarned() {
    return this.state.totalGoldEarned;
  }
  getPrestigePoints() {
    return this.state.prestigePoints;
  }

  /** Generator display info for one row, respecting the active buy mode. */
  generatorInfo(id, mode) {
    const g = generators.find((x) => x.id === id);
    const owned = this.state.generators[id] || 0;
    const nextCost = cost(g.baseCost, owned);
    let buyCount;
    if (mode === 'max') {
      buyCount = maxAffordable(g.baseCost, owned, this.state.gold);
    } else {
      buyCount = mode === '10' ? 10 : 1;
    }
    const batchCost = costForN(g.baseCost, owned, Math.max(1, buyCount));
    // Locked until the player can roughly afford it (gold >= 50% first cost).
    const unlocked = owned > 0 || this.state.gold >= g.baseCost * 0.5;
    const affordable = this.state.gold >= nextCost;
    return {
      def: g,
      owned,
      nextCost,
      buyCount: mode === 'max' ? buyCount : buyCount,
      batchCost,
      unlocked,
      affordable,
      kpsContribution: owned * g.baseKps * this.mult,
      perUnit: g.baseKps * this.mult,
    };
  }

  /** Tap-upgrade display info for one row. */
  tapUpgradeInfo(id, mode) {
    const u = tapUpgrades.find((x) => x.id === id);
    const level = this.state.tapLevels[id] || 0;
    const remaining = (u.maxLevel ?? Infinity) - level;
    const isMax = remaining <= 0;
    const nextCost = cost(u.baseCost, level);
    let buyCount = mode === 'max' ? maxAffordable(u.baseCost, level, this.state.gold, remaining) : mode === '10' ? Math.min(10, remaining) : Math.min(1, remaining);
    const batchCost = costForN(u.baseCost, level, Math.max(1, buyCount));
    const affordable = !isMax && this.state.gold >= nextCost;
    return {
      def: u,
      level,
      nextCost,
      buyCount,
      batchCost,
      isMax,
      affordable,
      bonusPerLevel: u.tapBonus * this.mult,
    };
  }

  // ------------------------------------------------------------------
  // Prestige
  // ------------------------------------------------------------------
  canPrestige() {
    return this.state.totalGoldEarned >= PRESTIGE_UNLOCK;
  }

  /** Prestige points the player would GAIN by resetting now. */
  pendingPrestige() {
    return Math.floor(Math.sqrt(this.state.totalGoldEarned / 1e6));
  }

  doPrestige() {
    if (!this.canPrestige()) return false;
    const gained = this.pendingPrestige();
    if (gained <= 0) return false;
    this.state.prestigePoints += gained;
    // Soft reset: gold + generator levels + tap levels, keep prestige + total?
    // Spec: resets gold + generator levels; prestige persists. Keep total at 0
    // so the next prestige requires fresh progress (standard idle behavior).
    this.state.gold = 0;
    this.state.totalGoldEarned = 0;
    this.state.generators = {};
    this.state.tapLevels = {};
    this.recompute();
    // Rebuild world progress (stage resets to 0 visually).
    this.world.tower.stage = 0;
    for (let s = 0; s < this.world.tower.stageGroups.length; s++) {
      const g = this.world.tower.stageGroups[s];
      g.visible = s === 0;
      g.scale.set(1, 1, 1);
    }
    this.world.tower.setProgress(0);
    this.world.smoke.setIntensity(this.world.tower.intensity());
    this.world.sceneManager.setHearth(this.world.tower.intensity() * 1.2);
    this.save();
    this._emitChange();
    return gained;
  }

  static get PRESTIGE_UNLOCK() {
    return PRESTIGE_UNLOCK;
  }

  // ------------------------------------------------------------------
  // Persistence
  // ------------------------------------------------------------------
  save() {
    SaveSystem.save(this.state);
  }

  hardReset() {
    SaveSystem.clear();
    this.state = SaveSystem.defaultState();
    this.recompute();
    this.world.tower.stage = 0;
    for (let s = 0; s < this.world.tower.stageGroups.length; s++) {
      const g = this.world.tower.stageGroups[s];
      g.visible = s === 0;
      g.scale.set(1, 1, 1);
    }
    this.world.tower.setProgress(0);
    this.world.smoke.setIntensity(this.world.tower.intensity());
    this.world.sceneManager.setHearth(this.world.tower.intensity() * 1.2);
    this.save();
    this._emitChange();
  }
}
