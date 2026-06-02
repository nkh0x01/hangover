// ShopSheet.js
// Sliding bottom sheet with two tabs. Tab A: ingredient-supply upgrades and
// gadgets (Tier 1–3) with x1/x10/Max buying and progressive reveal. Tab B is
// rendered by SharesPanel. Handles drag-to-expand/collapse + tap-to-toggle.

import { ingredients } from '../data/ingredients.js';
import { gadgets } from '../data/gadgets.js';

const HANDLE_H = 56;

export class ShopSheet {
  constructor(game, i18n, input) {
    this.game = game;
    this.i18n = i18n;
    this.input = input;
    this.economy = game.economy;

    this.sheet = document.getElementById('shop-sheet');
    this.handle = document.getElementById('shop-handle');
    this.techPanel = document.getElementById('tab-tech');
    this.sharesPanelEl = document.getElementById('tab-shares');
    this.tabs = Array.from(document.querySelectorAll('.shop-tab'));

    this.expanded = false;
    this.buyMode = this.game.state.buyMode || 1;
    this.revealed = new Set();
    this.rows = {}; // id -> { el, type, def }
    this._amountBtns = [];

    this.sharesPanel = null; // set externally
  }

  init() {
    this._buildTechTab();
    this._wireTabs();
    this._wireDrag();
    this._applyTransform();
    return this;
  }

  setSharesPanel(panel) {
    this.sharesPanel = panel;
  }

  // ---- tab A: ingredients & tech ----------------------------------------
  _buildTechTab() {
    const t = this.i18n.t.bind(this.i18n);
    this.techPanel.innerHTML = '';

    // Buy-amount selector.
    const bar = document.createElement('div');
    bar.className = 'buy-amount-bar';
    [
      { mode: 1, key: 'buy_x1' },
      { mode: 10, key: 'buy_x10' },
      { mode: 'max', key: 'buy_max' },
    ].forEach(({ mode, key }) => {
      const b = document.createElement('button');
      b.className = 'amount-btn' + (this._sameMode(mode) ? ' active' : '');
      b.setAttribute('data-i18n', key);
      b.textContent = t(key);
      b.addEventListener('click', () => this._setBuyMode(mode));
      bar.appendChild(b);
      this._amountBtns.push({ el: b, mode });
    });
    this.techPanel.appendChild(bar);

    // Ingredients section.
    const ingTitle = document.createElement('div');
    ingTitle.className = 'shop-section-title';
    ingTitle.setAttribute('data-i18n', 'ingredients');
    ingTitle.textContent = t('ingredients');
    this.techPanel.appendChild(ingTitle);
    ingredients.forEach((def) => this._addRow('ingredient', def));

    // Stations / tech section.
    const techTitle = document.createElement('div');
    techTitle.className = 'shop-section-title';
    techTitle.setAttribute('data-i18n', 'tech');
    techTitle.textContent = t('tech');
    this.techPanel.appendChild(techTitle);
    gadgets.forEach((def) => this._addRow('gadget', def));

    this.refresh();
  }

  _addRow(type, def) {
    const row = document.createElement('div');
    row.className = 'buy-row';
    if (def.tier === 3) row.classList.add('tier3-row');

    const icon = document.createElement('div');
    icon.className = 'row-icon';
    icon.textContent = def.icon;

    const info = document.createElement('div');
    info.className = 'row-info';
    const name = document.createElement('div');
    name.className = 'row-name';
    name.setAttribute('data-i18n', def.nameKey);
    name.textContent = this.i18n.t(def.nameKey);
    const sub = document.createElement('div');
    sub.className = 'row-sub';
    info.appendChild(name);
    info.appendChild(sub);

    const buyWrap = document.createElement('div');
    buyWrap.className = 'row-buy';
    const buyBtn = document.createElement('button');
    buyBtn.className = 'buy-btn' + (def.tier === 3 ? ' buy-tier3' : '');
    const cost = document.createElement('span');
    cost.className = 'b-cost';
    const label = document.createElement('span');
    label.className = 'b-label';
    buyBtn.appendChild(label);
    buyBtn.appendChild(cost);
    buyBtn.addEventListener('click', () => this._buy(type, def));
    buyWrap.appendChild(buyBtn);

    row.appendChild(icon);
    row.appendChild(info);
    row.appendChild(buyWrap);
    this.techPanel.appendChild(row);

    this.rows[def.id] = { el: row, sub, buyBtn, cost, label, type, def };
  }

  _buy(type, def) {
    const res = type === 'ingredient' ? this.game.buyIngredient(def.id, this.buyMode) : this.game.buyGadget(def.id, this.buyMode);
    if (res.ok) {
      this.refresh();
    } else if (this.onCannotAfford) {
      this.onCannotAfford();
    }
  }

  _sameMode(mode) {
    return this.buyMode === mode;
  }
  _setBuyMode(mode) {
    this.buyMode = mode;
    this.game.state.buyMode = mode;
    this._amountBtns.forEach(({ el, mode: m }) => el.classList.toggle('active', m === mode));
    this.refresh();
  }

  // ---- dynamic refresh ---------------------------------------------------
  refresh() {
    const t = this.i18n.t.bind(this.i18n);
    const locale = this.i18n.getLanguage();
    const gold = this.game.state.gold;
    const fmt = (n) => this.economy.format(n, locale);
    const rate = (n) => this.economy.formatRate(n, locale);

    for (const id in this.rows) {
      const r = this.rows[id];
      const def = r.def;
      const isIng = r.type === 'ingredient';
      const level = isIng ? this.game.state.ingredients[id] || 0 : this.game.state.gadgets[id] || 0;

      // Progressive reveal: ingredients always shown; gadgets at 40% of first cost.
      // Tier-3 always shown (with financing hint).
      let revealed = isIng || def.tier === 3 || this.revealed.has(id);
      if (!revealed && gold >= def.baseCost * 0.4) {
        this.revealed.add(id);
        revealed = true;
      }
      r.el.style.display = revealed ? '' : 'none';
      if (!revealed) continue;

      // Sub line describing the upgrade and current contribution.
      r.sub.innerHTML = this._subText(r.type, def, level, fmt, rate, t);

      // Cost for the active buy mode.
      const { count, totalCost } = this.economy.resolvePurchase(def.baseCost, level, gold, this.buyMode);
      const affordable = count > 0 && totalCost <= gold;
      const modeLabel = this.buyMode === 'max' ? (count > 0 ? '×' + count : t('buy_max')) : 'x' + this.buyMode;
      r.label.textContent = modeLabel;
      r.cost.textContent = '🪙 ' + fmt(totalCost);
      r.buyBtn.disabled = !affordable;
      r.el.classList.toggle('locked', def.tier === 3 && level === 0 && !affordable);
    }
  }

  _subText(type, def, level, fmt, rate, t) {
    if (type === 'ingredient') {
      const supply = def.baseSupply * (1 + level);
      return `${t('level')} ${level} · ${t('supply')}: <b>${rate(supply)}</b>`;
    }
    // Gadgets
    if (def.kind === 'station') {
      const stationRate = def.baseRate * level;
      return `${t('owned')} ${level} · <b>${rate(stationRate)}</b> ${t('rate_per_sec', { value: '' }).trim()}`;
    }
    if (def.kind === 'goldMult') {
      const pct = Math.round(def.effect * 100);
      return `${t('level')} ${level} · +${pct}% ${t('gold')} · ×${(1 + def.effect * level).toFixed(2)}`;
    }
    if (def.kind === 'globalMult') {
      const pct = Math.round(def.effect * 100);
      return `${t('level')} ${level} · +${pct}% · ×${(1 + def.effect * level).toFixed(2)}`;
    }
    if (def.kind === 'tier3') {
      const pct = Math.round(def.mult * 100);
      const hint = level === 0 ? ` · 💰 ${t('shares_title')}` : '';
      return `${t('level')} ${level} · +${pct}% ⚙️ · ×${(1 + def.mult * level).toFixed(2)}${hint}`;
    }
    return '';
  }

  /** Re-render text on language change. */
  relocalize() {
    // Rebuild labels that are static; dynamic via refresh.
    this.i18n.applyBindings(this.techPanel);
    this.refresh();
  }

  // ---- tabs --------------------------------------------------------------
  _wireTabs() {
    this.tabs.forEach((tab) => {
      tab.addEventListener('click', () => this._selectTab(tab.getAttribute('data-tab')));
    });
  }
  _selectTab(name) {
    this.tabs.forEach((t) => t.classList.toggle('active', t.getAttribute('data-tab') === name));
    document.getElementById('tab-tech').classList.toggle('active', name === 'tech');
    document.getElementById('tab-shares').classList.toggle('active', name === 'shares');
    if (name === 'shares' && this.sharesPanel) this.sharesPanel.refresh();
    if (!this.expanded) this.expand();
  }

  // ---- drag / expand -----------------------------------------------------
  _wireDrag() {
    const handle = this.handle;
    let dragging = false;
    let startY = 0;
    let startTranslate = 0;
    let moved = 0;

    const maxTranslate = () => this.sheet.getBoundingClientRect().height - HANDLE_H;

    const onDown = (e) => {
      dragging = true;
      moved = 0;
      startY = e.clientY;
      startTranslate = this._currentTranslate();
      this.sheet.style.transition = 'none';
      handle.setPointerCapture && handle.setPointerCapture(e.pointerId);
    };
    const onMove = (e) => {
      if (!dragging) return;
      const dy = e.clientY - startY;
      moved = Math.max(moved, Math.abs(dy));
      let ty = startTranslate + dy;
      ty = Math.max(0, Math.min(maxTranslate(), ty));
      this.sheet.style.transform = `translateY(${ty}px)`;
    };
    const onUp = (e) => {
      if (!dragging) return;
      dragging = false;
      this.sheet.style.transition = '';
      if (moved < 8) {
        // Treat as a tap -> toggle.
        this.toggle();
      } else {
        const ty = this._currentTranslate();
        const mid = maxTranslate() / 2;
        if (ty < mid) this.expand();
        else this.collapse();
      }
    };

    handle.addEventListener('pointerdown', onDown);
    handle.addEventListener('pointermove', onMove);
    handle.addEventListener('pointerup', onUp);
    handle.addEventListener('pointercancel', onUp);
  }

  _currentTranslate() {
    const m = this.sheet.style.transform.match(/translateY\(([-\d.]+)px\)/);
    if (m) return parseFloat(m[1]);
    return this.expanded ? 0 : this.sheet.getBoundingClientRect().height - HANDLE_H;
  }

  _applyTransform() {
    const maxT = this.sheet.getBoundingClientRect().height - HANDLE_H;
    this.sheet.style.transform = `translateY(${this.expanded ? 0 : maxT}px)`;
  }

  expand() {
    this.expanded = true;
    this.sheet.style.transform = 'translateY(0px)';
  }
  collapse() {
    this.expanded = false;
    const maxT = this.sheet.getBoundingClientRect().height - HANDLE_H;
    this.sheet.style.transform = `translateY(${maxT}px)`;
  }
  toggle() {
    this.expanded ? this.collapse() : this.expand();
  }
}

export default ShopSheet;
