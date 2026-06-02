// SharesSystem.js
// Investor / equity meta. The player sells equity for instant lump sums
// (enabling Tier-3 machinery early) at the permanent cost of a lower passive
// multiplier (ownerShare). Manual tapping is never affected.

import { investors } from '../data/investors.js';

export const VALUATION_MULTIPLE = 600;
export const BUYBACK_PREMIUM = 1.5;
export const EQUITY_CAP = 0.8;
export const BUYBACK_CHUNK = 0.05; // smallest equity chunk bought back at a time

export class SharesSystem {
  constructor(economy) {
    this.economy = economy;
    this.investors = investors;
  }

  // ---- derived -----------------------------------------------------------
  ownerShare(state) {
    return Math.max(0, 1 - (state.shares.equitySold || 0));
  }

  /** Live business valuation from whole-business gross revenue/sec. */
  valuation(state) {
    return this.economy.grossGoldPerSec(state) * VALUATION_MULTIPLE;
  }

  /** Lump sum an investor pays for one round at the current valuation. */
  lumpSumFor(investor, valuation) {
    return valuation * investor.equityPerRound * investor.cashMultiple;
  }

  // ---- cooldowns ---------------------------------------------------------
  cooldownRemaining(state, investor, now) {
    const until = (state.shares.cooldowns && state.shares.cooldowns[investor.id]) || 0;
    return Math.max(0, Math.ceil((until - now) / 1000));
  }

  /**
   * Clamp cooldowns on load so offline wall-time isn't unfair and a corrupt /
   * future-dated timestamp can never strand an investor beyond its real window.
   */
  clampCooldowns(state, now) {
    if (!state.shares.cooldowns) state.shares.cooldowns = {};
    for (const inv of this.investors) {
      const until = state.shares.cooldowns[inv.id] || 0;
      const maxUntil = now + inv.cooldownSec * 1000;
      if (until > maxUntil) state.shares.cooldowns[inv.id] = maxUntil;
      if (until < 0) state.shares.cooldowns[inv.id] = 0;
    }
  }

  // ---- raise -------------------------------------------------------------
  /**
   * Whether a round can be raised right now.
   * Returns { ok, reason, params } — reason is an i18n key when blocked.
   */
  canRaise(state, investor, now) {
    const val = this.valuation(state);
    if (val < investor.minValuation) {
      return { ok: false, reason: 'min_valuation', params: { value: investor.minValuation } };
    }
    const remaining = this.cooldownRemaining(state, investor, now);
    if (remaining > 0) {
      return { ok: false, reason: 'cooldown_wait', params: { value: remaining } };
    }
    if ((state.shares.equitySold || 0) + investor.equityPerRound > EQUITY_CAP + 1e-9) {
      return { ok: false, reason: 'equity_cap_reached', params: {} };
    }
    return { ok: true };
  }

  /** Execute a raise. Returns { ok, lumpSum } or { ok:false, reason }. */
  raise(state, investor, now) {
    const check = this.canRaise(state, investor, now);
    if (!check.ok) return check;
    const val = this.valuation(state);
    const lumpSum = this.lumpSumFor(investor, val);
    state.gold += lumpSum;
    state.totalGoldEarned += lumpSum;
    state.shares.equitySold = Math.min(EQUITY_CAP, (state.shares.equitySold || 0) + investor.equityPerRound);
    if (!state.shares.cooldowns) state.shares.cooldowns = {};
    state.shares.cooldowns[investor.id] = now + investor.cooldownSec * 1000;
    return { ok: true, lumpSum };
  }

  // ---- buyback -----------------------------------------------------------
  /** Cost in gold to buy back a given equity fraction. */
  buybackCost(valuation, equityToBuy) {
    return valuation * equityToBuy * BUYBACK_PREMIUM;
  }

  /** How much equity a single buyback chunk would restore (clamped). */
  nextBuybackChunk(state) {
    return Math.min(BUYBACK_CHUNK, state.shares.equitySold || 0);
  }

  canBuyback(state) {
    const chunk = this.nextBuybackChunk(state);
    if (chunk <= 0) return { ok: false, reason: 'buyback_none' };
    const cost = this.buybackCost(this.valuation(state), chunk);
    if (state.gold < cost) return { ok: false, reason: 'cannot_afford' };
    return { ok: true, chunk, cost };
  }

  /** Execute a one-chunk buyback. Returns { ok, cost, chunk } or failure. */
  buyback(state) {
    const check = this.canBuyback(state);
    if (!check.ok) return check;
    state.gold -= check.cost;
    state.shares.equitySold = Math.max(0, (state.shares.equitySold || 0) - check.chunk);
    return { ok: true, cost: check.cost, chunk: check.chunk };
  }
}

export default SharesSystem;
