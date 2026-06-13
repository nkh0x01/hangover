/**
 * LocalizationManager
 * -------------------
 * Loads `public/lang/lang.json` once at startup, exposes a `t(key, params)`
 * translator with `{value}` interpolation, and live-rebinds any DOM nodes
 * carrying `data-i18n` / `data-i18n-attr` attributes when the language changes.
 *
 * Supported languages: ka (canonical), en, ru, de.
 */

const STORAGE_KEY = 'khinkali.lang';
const FALLBACK = 'ka';
const SUPPORTED = ['ka', 'en', 'ru', 'de'];

export class LocalizationManager {
  constructor() {
    this.dict = {};
    this.lang = FALLBACK;
    this._listeners = new Set();
  }

  /** Fetch the language file and pick the initial language. Call before UI render. */
  async load() {
    // Vite serves `public/` at the app base; use a relative URL so it also
    // works when wrapped by Capacitor (file:// base).
    const url = new URL('lang/lang.json', document.baseURI).href;
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`Failed to load lang.json: ${res.status}`);
    }
    this.dict = await res.json();
    this.lang = this._detectInitialLanguage();
    document.documentElement.setAttribute('lang', this.lang);
    return this;
  }

  /** Decide which language to start in: stored > navigator > fallback. */
  _detectInitialLanguage() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored && SUPPORTED.includes(stored)) return stored;

    const nav = (navigator.language || '').toLowerCase();
    for (const code of SUPPORTED) {
      if (nav.startsWith(code)) return code;
    }
    // Map a few common locale roots that don't share the code letters.
    if (nav.startsWith('en')) return 'en';
    return FALLBACK;
  }

  /** Translate a key with optional `{param}` interpolation. */
  t(key, params) {
    const table = this.dict[this.lang] || this.dict[FALLBACK] || {};
    let str = table[key];
    if (str == null) {
      // Last-resort fallback chain so the UI never shows `undefined`.
      str = (this.dict[FALLBACK] && this.dict[FALLBACK][key]) || key;
    }
    if (params) {
      str = str.replace(/\{(\w+)\}/g, (m, name) =>
        params[name] != null ? params[name] : m
      );
    }
    return str;
  }

  getLanguage() {
    return this.lang;
  }

  /** List available languages as `{ code, name }`. */
  available() {
    return SUPPORTED.filter((c) => this.dict[c]).map((c) => ({
      code: c,
      name: this.dict[c].lang_name || c,
    }));
  }

  /** Switch language, persist, re-scan all bound DOM nodes, notify listeners. */
  setLanguage(code) {
    if (!SUPPORTED.includes(code) || code === this.lang) {
      if (code === this.lang) this.applyBindings();
      return;
    }
    this.lang = code;
    localStorage.setItem(STORAGE_KEY, code);
    document.documentElement.setAttribute('lang', code);
    this.applyBindings();
    this._listeners.forEach((fn) => fn(code));
  }

  /** Register a callback fired whenever the language changes. */
  onChange(fn) {
    this._listeners.add(fn);
    return () => this._listeners.delete(fn);
  }

  /**
   * Scan the document for translation-bound nodes and update them.
   * - `data-i18n="key"` sets textContent.
   * - `data-i18n-attr="attr:key,attr2:key2"` sets attributes.
   * Both support a JSON params object in `data-i18n-params`.
   */
  applyBindings(root = document) {
    const textNodes = root.querySelectorAll('[data-i18n]');
    textNodes.forEach((el) => {
      const key = el.getAttribute('data-i18n');
      const params = this._readParams(el);
      el.textContent = this.t(key, params);
    });

    const attrNodes = root.querySelectorAll('[data-i18n-attr]');
    attrNodes.forEach((el) => {
      const spec = el.getAttribute('data-i18n-attr');
      const params = this._readParams(el);
      spec.split(',').forEach((pair) => {
        const [attr, key] = pair.split(':').map((s) => s.trim());
        if (attr && key) el.setAttribute(attr, this.t(key, params));
      });
    });
  }

  _readParams(el) {
    const raw = el.getAttribute('data-i18n-params');
    if (!raw) return undefined;
    try {
      return JSON.parse(raw);
    } catch {
      return undefined;
    }
  }
}

// Single shared instance used across the app.
export const i18n = new LocalizationManager();
