// PrepTable.js
// Middle section: ingredient slots (tap = cosmetic buffer bump + FX), machine
// slots (grey until owned, animate while producing), the central "Assemble"
// tap target, and a bottleneck badge pointing at the cheapest fix.

import { ingredients } from '../data/ingredients.js';
import { gadgets, gadgetById } from '../data/gadgets.js';
import { squishStretch } from '../fx/Tween.js';

// Station gadgets shown as machine slots (the 5-step chain).
const STATION_GADGETS = ['doughMixer', 'meatGrinder', 'herbChopper', 'pleatingRobot', 'steamCauldron'];

export class PrepTable {
  constructor(game, i18n, input, particles) {
    this.game = game;
    this.i18n = i18n;
    this.input = input;
    this.particles = particles;
    this.economy = game.economy;
    this.zone = document.getElementById('prep-zone');
    this.ingEls = {};
    this.machEls = {};
  }

  init() {
    this._build();
    return this;
  }

  _build() {
    const t = this.i18n.t.bind(this.i18n);
    this.zone.innerHTML = '';

    // Bottleneck badge.
    this.badge = document.createElement('div');
    this.badge.className = 'prep-bottleneck';
    this.badge.innerHTML = `<span class="bn-icon">⚠️</span><span class="bn-text"></span>`;
    this.bnText = this.badge.querySelector('.bn-text');
    this.zone.appendChild(this.badge);

    // Ingredient slots.
    const ingRow = document.createElement('div');
    ingRow.className = 'prep-ingredients';
    ingredients.forEach((def) => {
      const slot = document.createElement('div');
      slot.className = 'ing-slot';
      slot.innerHTML = `
        <span class="ing-icon">${def.icon}</span>
        <span class="ing-name" data-i18n="${def.nameKey}">${t(def.nameKey)}</span>
        <span class="ing-rate">0</span>
        <span class="ing-fill"><i></i></span>`;
      ingRow.appendChild(slot);
      this.ingEls[def.id] = {
        slot,
        rate: slot.querySelector('.ing-rate'),
        fill: slot.querySelector('.ing-fill > i'),
      };
      this.input.onTap(slot, (x, y) => this._onIngredientTap(def, x, y));
    });
    this.zone.appendChild(ingRow);

    // Central assemble target.
    const center = document.createElement('div');
    center.className = 'prep-center';
    this.assembleBtn = document.createElement('button');
    this.assembleBtn.id = 'assemble-btn';
    this.assembleBtn.innerHTML = `
      <span class="assemble-emoji">🥟</span>
      <span class="assemble-label" data-i18n="tap_to_assemble">${t('tap_to_assemble')}</span>`;
    center.appendChild(this.assembleBtn);
    this.zone.appendChild(center);
    this.input.onTap(this.assembleBtn, (x, y) => this._onAssembleTap(x, y), { preventScroll: true });

    // Machine slots.
    const machRow = document.createElement('div');
    machRow.className = 'prep-machines';
    STATION_GADGETS.forEach((id) => {
      const def = gadgetById[id];
      const slot = document.createElement('div');
      slot.className = 'mach-slot';
      slot.innerHTML = `${def.icon}<span class="mach-count">0</span>`;
      machRow.appendChild(slot);
      this.machEls[id] = { slot, count: slot.querySelector('.mach-count') };
    });
    this.zone.appendChild(machRow);
  }

  _onIngredientTap(def, x, y) {
    this.game.tapIngredient(def.id);
    const el = this.ingEls[def.id];
    squishStretch(el.slot, 0.28);
    this.particles.spawnPop(x, y, def.icon, 2);
  }

  _onAssembleTap(x, y) {
    const res = this.game.manualTap();
    squishStretch(this.assembleBtn, res.golden ? 0.5 : 0.32);
    const locale = this.i18n.getLanguage();
    const goldStr = '+' + this.economy.format(res.gold, locale);
    this.particles.spawnPop(x, y, '🥟', res.golden ? 6 : 3);
    this.particles.spawnText(x, y - 10, goldStr, res.golden ? 'golden' : '');
    this.particles.spawnText(x, y + 14, '+1 🥟', 'khinkali');
    if (res.golden && this.onGolden) this.onGolden();
  }

  refresh() {
    const locale = this.i18n.getLanguage();
    const t = this.i18n.t.bind(this.i18n);
    const rate = (n) => this.economy.formatRate(n, locale);

    // Ingredient slots: supply rate + buffer fill.
    ingredients.forEach((def) => {
      const el = this.ingEls[def.id];
      const supply = this.economy.supplyRate(this.game.state, def.id);
      el.rate.textContent = rate(supply);
      const buf = this.game.buffers[def.id] || 0;
      el.fill.style.width = (buf * 100).toFixed(0) + '%';
    });

    // Machine slots: owned + producing animation.
    const producing = this.game.cache.autoKPS > 0;
    STATION_GADGETS.forEach((id) => {
      const owned = this.game.state.gadgets[id] || 0;
      const m = this.machEls[id];
      m.count.textContent = owned;
      m.slot.classList.toggle('owned', owned > 0);
      m.slot.classList.toggle('producing', owned > 0 && producing);
    });

    // Bottleneck badge.
    const bn = this.game.getBottleneck();
    let fixName = '';
    if (bn.fixType === 'ingredient') {
      const d = ingredients.find((i) => i.id === bn.fixId);
      fixName = d ? t(d.nameKey) : '';
    } else {
      const d = gadgetById[bn.fixId];
      fixName = d ? t(d.nameKey) : '';
    }
    this.bnText.textContent = `${t(bn.labelKey)} · ${t('bn_fix_hint', { name: fixName })}`;
  }

  relocalize() {
    this.i18n.applyBindings(this.zone);
    this.refresh();
  }
}

export default PrepTable;
