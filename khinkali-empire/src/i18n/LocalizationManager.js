// LocalizationManager
// Bundled-module localization (no runtime fetch) so it loads reliably under
// any GitHub Pages sub-path. Supports KA / EN / RU / DE with live re-binding.

const STORAGE_KEY = 'khinkali.lang';
const FALLBACK = 'ka';

class LocalizationManager {
  constructor() {
    this.data = {};
    this.lang = FALLBACK;
    this._listeners = new Set();
  }

  /**
   * Initialize with bundled localization data and pick the active language.
   * Priority: saved choice -> navigator.language -> KA fallback.
   */
  init(data) {
    this.data = data || {};
    const saved = this._readSaved();
    const codes = this.available().map((l) => l.code);
    let pick = FALLBACK;
    if (saved && codes.includes(saved)) {
      pick = saved;
    } else {
      const nav = (navigator.language || '').slice(0, 2).toLowerCase();
      if (codes.includes(nav)) pick = nav;
    }
    this.lang = pick;
    return this;
  }

  _readSaved() {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return null;
    }
  }

  /** Translate a key with optional {param} interpolation. */
  t(key, params) {
    const table = this.data[this.lang] || {};
    let str = table[key];
    if (str === undefined) {
      // Fall back to KA, then to the raw key so nothing renders blank.
      const fb = this.data[FALLBACK] || {};
      str = fb[key] !== undefined ? fb[key] : key;
    }
    if (params) {
      str = str.replace(/\{(\w+)\}/g, (m, name) =>
        params[name] !== undefined ? params[name] : m
      );
    }
    return str;
  }

  /** Switch language live and re-render all bound DOM + notify listeners. */
  setLanguage(code) {
    if (!this.data[code]) return;
    this.lang = code;
    try {
      localStorage.setItem(STORAGE_KEY, code);
    } catch (e) {
      /* ignore quota / private mode */
    }
    document.documentElement.setAttribute('lang', code);
    this.applyBindings(document);
    this._listeners.forEach((fn) => fn(code));
  }

  getLanguage() {
    return this.lang;
  }

  /** List of { code, name } for available languages. */
  available() {
    return Object.keys(this.data).map((code) => ({
      code,
      name: this.data[code].lang_name || code,
    }));
  }

  /** Subscribe to language changes; returns an unsubscribe function. */
  onChange(fn) {
    this._listeners.add(fn);
    return () => this._listeners.delete(fn);
  }

  /**
   * Scan a root for data-i18n / data-i18n-attr bindings and apply translations.
   * data-i18n="key"             -> textContent
   * data-i18n-attr="attr:key"   -> element attribute (comma-separated pairs)
   */
  applyBindings(root = document) {
    root.querySelectorAll('[data-i18n]').forEach((el) => {
      const key = el.getAttribute('data-i18n');
      el.textContent = this.t(key);
    });
    root.querySelectorAll('[data-i18n-attr]').forEach((el) => {
      const spec = el.getAttribute('data-i18n-attr');
      spec.split(',').forEach((pair) => {
        const [attr, key] = pair.split(':').map((s) => s.trim());
        if (attr && key) el.setAttribute(attr, this.t(key));
      });
    });
  }
}

// Single shared instance across the app.
export const i18n = new LocalizationManager();
export default i18n;
