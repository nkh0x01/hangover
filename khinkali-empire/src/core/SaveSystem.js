// SaveSystem.js
// localStorage serialize / deserialize for the full game state.

const KEY = 'khinkali.save.v1';

export class SaveSystem {
  constructor() {
    this.key = KEY;
  }

  /** Persist a plain serializable state object. Returns true on success. */
  save(state) {
    try {
      const payload = JSON.stringify(state);
      localStorage.setItem(this.key, payload);
      return true;
    } catch (e) {
      // Quota exceeded / private mode — fail silently, game keeps running.
      return false;
    }
  }

  /** Load and parse the saved state, or null if none / corrupt. */
  load() {
    try {
      const raw = localStorage.getItem(this.key);
      if (!raw) return null;
      const data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return null;
      return data;
    } catch (e) {
      return null;
    }
  }

  /** Remove the save entirely. */
  clear() {
    try {
      localStorage.removeItem(this.key);
      return true;
    } catch (e) {
      return false;
    }
  }
}

export const saveSystem = new SaveSystem();
export default saveSystem;
