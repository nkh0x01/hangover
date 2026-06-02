/**
 * SaveSystem
 * ----------
 * Serializes/deserializes the game state to localStorage and computes
 * offline earnings based on elapsed real time since the last save.
 */

const SAVE_KEY = 'khinkali.save.v1';
const OFFLINE_CAP_SECONDS = 8 * 60 * 60; // 8 hours

export class SaveSystem {
  /** Default state used for a brand-new player or after a hard reset. */
  static defaultState() {
    return {
      gold: 0,
      totalGoldEarned: 0,
      generators: {}, // id -> owned count
      tapLevels: {}, // id -> level
      prestigePoints: 0,
      lastSaved: Date.now(),
      version: 1,
    };
  }

  /** Persist state to localStorage. Returns true on success. */
  static save(state) {
    try {
      state.lastSaved = Date.now();
      localStorage.setItem(SAVE_KEY, JSON.stringify(state));
      return true;
    } catch (e) {
      console.warn('Save failed:', e);
      return false;
    }
  }

  /**
   * Load state. Returns `{ state, offline }` where `offline` describes any
   * earnings accrued while away: `{ seconds, gold }` (gold may be 0).
   */
  static load(kpsResolver) {
    const fresh = SaveSystem.defaultState();
    let raw;
    try {
      raw = localStorage.getItem(SAVE_KEY);
    } catch {
      raw = null;
    }
    if (!raw) {
      return { state: fresh, offline: { seconds: 0, gold: 0 } };
    }

    let parsed;
    try {
      parsed = JSON.parse(raw);
    } catch {
      return { state: fresh, offline: { seconds: 0, gold: 0 } };
    }

    // Merge defensively so missing keys never crash the game.
    const state = {
      ...fresh,
      ...parsed,
      generators: { ...parsed.generators },
      tapLevels: { ...parsed.tapLevels },
    };

    // Offline earnings: kps * min(elapsed, CAP).
    let offline = { seconds: 0, gold: 0 };
    const now = Date.now();
    const elapsedSec = Math.max(0, (now - (state.lastSaved || now)) / 1000);
    if (elapsedSec > 1 && typeof kpsResolver === 'function') {
      const kps = kpsResolver(state);
      const effective = Math.min(elapsedSec, OFFLINE_CAP_SECONDS);
      const earned = kps * effective;
      if (earned > 0) {
        state.gold += earned;
        state.totalGoldEarned += earned;
        offline = { seconds: effective, gold: earned };
      }
    }

    return { state, offline };
  }

  /** Remove the save entirely (hard reset). */
  static clear() {
    try {
      localStorage.removeItem(SAVE_KEY);
    } catch {
      /* ignore */
    }
  }

  static get OFFLINE_CAP_SECONDS() {
    return OFFLINE_CAP_SECONDS;
  }
}
