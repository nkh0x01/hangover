/**
 * UIManager
 * ---------
 * Builds and updates all DOM UI: HUD counters, the upgrade shop (tabs +
 * buy-mode), settings & dialog modals, language switching, and prestige.
 * All user-facing text comes from the i18n system; HUD numbers update every
 * frame from GameManager, while the shop rows refresh on change + a light tick.
 */

import { format } from '../core/Economy.js';
import { BottomSheet } from './BottomSheet.js';
import { GameManager } from '../core/GameManager.js';

export class UIManager {
  /**
   * @param {GameManager} game
   * @param {import('../i18n/LocalizationManager.js').LocalizationManager} i18n
   */
  constructor(game, i18n) {
    this.game = game;
    this.i18n = i18n;
    this.activeTab = 'generators';
    this.buyMode = '1';
    this._rowEls = new Map(); // id -> row element for cheap updates
    this._shopRefreshTimer = 0;
  }

  init() {
    this._cache();
    this._buildSheet();
    this._buildTabs();
    this._buildBuyModes();
    this._buildLanguageButtons();
    this._bindSettings();
    this._bindDialog();
    this._bindPrestige();

    // Apply i18n to all static bound nodes, and re-apply on language change.
    this.i18n.applyBindings();
    this.i18n.onChange(() => {
      this.i18n.applyBindings();
      this._renderShop();
      this._updateLangButtons();
      this._updatePrestigeButton();
    });

    // Refresh shop whenever game state changes (purchase, prestige, stage).
    this.game.onChange(() => {
      this._renderShop();
      this._updatePrestigeButton();
    });

    this._renderShop();
    this._updatePrestigeButton();
    this._firstTapDone = false;
  }

  _cache() {
    this.el = {
      gold: document.getElementById('gold-amount'),
      kps: document.getElementById('kps-readout'),
      tapHint: document.getElementById('tap-hint'),
      sheet: document.getElementById('bottom-sheet'),
      handle: document.getElementById('sheet-handle'),
      list: document.getElementById('upgrade-list'),
      tabs: document.querySelectorAll('.tab-btn'),
      buyModes: document.querySelectorAll('.buymode-btn'),
      settingsBtn: document.getElementById('settings-btn'),
      settingsModal: document.getElementById('settings-modal'),
      settingsClose: document.getElementById('settings-close'),
      langButtons: document.getElementById('lang-buttons'),
      statTotal: document.getElementById('stat-total-baked'),
      statPrestige: document.getElementById('stat-prestige'),
      resetBtn: document.getElementById('reset-btn'),
      dialog: document.getElementById('dialog-modal'),
      dialogTitle: document.getElementById('dialog-title'),
      dialogMsg: document.getElementById('dialog-message'),
      dialogConfirm: document.getElementById('dialog-confirm'),
      dialogCancel: document.getElementById('dialog-cancel'),
      prestigeBtn: document.getElementById('prestige-btn'),
      prestigePoints: document.getElementById('prestige-points-label'),
    };
  }

  _buildSheet() {
    this.sheet = new BottomSheet(this.el.sheet, this.el.handle);
    this.sheet.attach();
  }

  _buildTabs() {
    this.el.tabs.forEach((btn) => {
      btn.addEventListener('click', () => {
        this.activeTab = btn.dataset.tab;
        this.el.tabs.forEach((b) => b.classList.toggle('active', b === btn));
        this._renderShop();
        if (!this.sheet.expanded) this.sheet.open();
      });
    });
  }

  _buildBuyModes() {
    this.el.buyModes.forEach((btn) => {
      btn.addEventListener('click', () => {
        this.buyMode = btn.dataset.buymode;
        this.el.buyModes.forEach((b) => b.classList.toggle('active', b === btn));
        this._renderShop();
      });
    });
  }

  _buildLanguageButtons() {
    this.el.langButtons.innerHTML = '';
    this.i18n.available().forEach(({ code, name }) => {
      const btn = document.createElement('button');
      btn.className = 'lang-btn';
      btn.dataset.lang = code;
      btn.textContent = name;
      btn.addEventListener('click', () => this.i18n.setLanguage(code));
      this.el.langButtons.appendChild(btn);
    });
    this._updateLangButtons();
  }

  _updateLangButtons() {
    const cur = this.i18n.getLanguage();
    this.el.langButtons
      .querySelectorAll('.lang-btn')
      .forEach((b) => b.classList.toggle('active', b.dataset.lang === cur));
  }

  _bindSettings() {
    this.el.settingsBtn.addEventListener('click', () => {
      this.el.statTotal.textContent = format(
        this.game.getTotalEarned(),
        this.i18n.getLanguage()
      );
      this.el.statPrestige.textContent = format(
        this.game.getPrestigePoints(),
        this.i18n.getLanguage()
      );
      this.el.settingsModal.classList.remove('hidden');
    });
    this.el.settingsClose.addEventListener('click', () =>
      this.el.settingsModal.classList.add('hidden')
    );
    this.el.settingsModal.addEventListener('click', (e) => {
      if (e.target === this.el.settingsModal)
        this.el.settingsModal.classList.add('hidden');
    });
    this.el.resetBtn.addEventListener('click', () => {
      this.el.settingsModal.classList.add('hidden');
      this._confirm(
        this.i18n.t('reset_save'),
        this.i18n.t('reset_save') + '?',
        () => this.game.hardReset()
      );
    });
  }

  _bindDialog() {
    this.el.dialogCancel.addEventListener('click', () => this._closeDialog());
    this.el.dialog.addEventListener('click', (e) => {
      if (e.target === this.el.dialog) this._closeDialog();
    });
  }

  _bindPrestige() {
    this.el.prestigeBtn.addEventListener('click', () => {
      if (!this.game.canPrestige()) return;
      const pts = this.game.pendingPrestige();
      this._confirm(
        this.i18n.t('prestige'),
        this.i18n.t('prestige_confirm', {
          value: format(pts, this.i18n.getLanguage()),
        }),
        () => {
          const gained = this.game.doPrestige();
          if (gained) this.sheet.close();
        }
      );
    });
  }

  // ---- Generic dialog ----
  _confirm(title, message, onConfirm) {
    this.el.dialogTitle.textContent = title;
    this.el.dialogMsg.textContent = message;
    this.el.dialogConfirm.textContent = this.i18n.t('yes');
    this.el.dialogCancel.textContent = this.i18n.t('no');
    this.el.dialogCancel.classList.remove('hidden');
    this._dialogConfirmHandler = () => {
      this._closeDialog();
      onConfirm();
    };
    this.el.dialogConfirm.onclick = this._dialogConfirmHandler;
    this.el.dialog.classList.remove('hidden');
  }

  /** Info dialog with a single button (used for offline earnings). */
  showInfo(title, message, buttonKey = 'collect') {
    this.el.dialogTitle.textContent = title;
    this.el.dialogMsg.textContent = message;
    this.el.dialogConfirm.textContent = this.i18n.t(buttonKey);
    this.el.dialogCancel.classList.add('hidden');
    this.el.dialogConfirm.onclick = () => this._closeDialog();
    this.el.dialog.classList.remove('hidden');
  }

  _closeDialog() {
    this.el.dialog.classList.add('hidden');
    this.el.dialogConfirm.onclick = null;
  }

  // ---- Shop rendering ----
  _renderShop() {
    const lang = this.i18n.getLanguage();
    const list = this.el.list;
    list.innerHTML = '';
    this._rowEls.clear();

    if (this.activeTab === 'generators') {
      for (const g of this.game.generators) {
        const info = this.game.generatorInfo(g.id, this.buyMode);
        const row = this._makeGeneratorRow(g, info, lang);
        list.appendChild(row);
        this._rowEls.set('g_' + g.id, row);
      }
    } else {
      for (const u of this.game.tapUpgrades) {
        const info = this.game.tapUpgradeInfo(u.id, this.buyMode);
        const row = this._makeTapRow(u, info, lang);
        list.appendChild(row);
        this._rowEls.set('t_' + u.id, row);
      }
    }
  }

  _makeGeneratorRow(g, info, lang) {
    const row = document.createElement('div');
    row.className = 'upgrade-row';
    this._applyRowState(row, info, false);

    const name = this.i18n.t(g.nameKey);
    const costStr = info.unlocked
      ? format(info.batchCost, lang)
      : this.i18n.t('locked');

    row.innerHTML = `
      <div class="row-icon">${g.icon}</div>
      <div class="row-info">
        <div class="row-name">${name}</div>
        <div class="row-sub">+${format(g.baseKps * (this.game.mult || 1), lang)} ${this.i18n.t('kps_label')}</div>
      </div>
      <div class="row-buy">
        <div class="row-cost ${info.affordable ? '' : 'cant'}">${costStr}</div>
        <div class="row-owned">${this.i18n.t('owned')}: ${format(info.owned, lang)}</div>
      </div>
    `;

    row.addEventListener('click', () => {
      if (this.game.buyGenerator(g.id, this.buyMode)) {
        this._pulse(row);
      }
    });
    return row;
  }

  _makeTapRow(u, info, lang) {
    const row = document.createElement('div');
    row.className = 'upgrade-row';
    // Tap rows are always unlocked (treat unlocked as true).
    this._applyRowState(row, { ...info, unlocked: true }, info.isMax);

    const name = this.i18n.t(u.nameKey);
    const costStr = info.isMax
      ? this.i18n.t('max_level')
      : format(info.batchCost, lang);

    row.innerHTML = `
      <div class="row-icon">${u.icon}</div>
      <div class="row-info">
        <div class="row-name">${name}</div>
        <div class="row-sub">+${format(u.tapBonus * (this.game.mult || 1), lang)} / ${this.i18n.t('tap_power')}</div>
      </div>
      <div class="row-buy">
        <div class="row-cost ${info.affordable ? '' : 'cant'}">${costStr}</div>
        <div class="row-owned">${this.i18n.t('level')}: ${format(info.level, lang)}</div>
      </div>
    `;

    row.addEventListener('click', () => {
      if (this.game.buyTapUpgrade(u.id, this.buyMode)) {
        this._pulse(row);
      }
    });
    return row;
  }

  _applyRowState(row, info, isMax) {
    row.classList.remove('affordable', 'unaffordable', 'locked');
    if (!info.unlocked) {
      row.classList.add('locked');
    } else if (isMax) {
      row.classList.add('unaffordable');
    } else if (info.affordable) {
      row.classList.add('affordable');
    } else {
      row.classList.add('unaffordable');
    }
  }

  _pulse(row) {
    row.style.transform = 'scale(1.04)';
    setTimeout(() => (row.style.transform = ''), 90);
  }

  _updatePrestigeButton() {
    const can = this.game.canPrestige();
    this.el.prestigeBtn.disabled = !can;
    const lang = this.i18n.getLanguage();
    if (can) {
      const pts = this.game.pendingPrestige();
      this.el.prestigePoints.textContent = `+${format(pts, lang)}`;
    } else {
      this.el.prestigePoints.textContent = this.i18n.t('prestige_locked');
    }
  }

  // ---- Per-frame HUD update ----
  update(dt) {
    const lang = this.i18n.getLanguage();
    this.el.gold.textContent = format(this.game.getGold(), lang);
    this.el.kps.textContent = this.i18n.t('per_second', {
      value: format(this.game.getKPS(), lang),
    });

    // Light shop refresh so affordability/max counts stay live without
    // rebuilding the DOM every frame.
    this._shopRefreshTimer += dt;
    if (this._shopRefreshTimer >= 0.4) {
      this._shopRefreshTimer = 0;
      this._refreshShopAffordability(lang);
    }
  }

  /** Update only the dynamic bits (cost colour, counts) of existing rows. */
  _refreshShopAffordability(lang) {
    if (this.activeTab === 'generators') {
      for (const g of this.game.generators) {
        const row = this._rowEls.get('g_' + g.id);
        if (!row) continue;
        const info = this.game.generatorInfo(g.id, this.buyMode);
        this._applyRowState(row, info, false);
        const costEl = row.querySelector('.row-cost');
        costEl.textContent = info.unlocked
          ? format(info.batchCost, lang)
          : this.i18n.t('locked');
        costEl.classList.toggle('cant', !info.affordable);
        const ownedEl = row.querySelector('.row-owned');
        ownedEl.textContent = `${this.i18n.t('owned')}: ${format(info.owned, lang)}`;
      }
    } else {
      for (const u of this.game.tapUpgrades) {
        const row = this._rowEls.get('t_' + u.id);
        if (!row) continue;
        const info = this.game.tapUpgradeInfo(u.id, this.buyMode);
        this._applyRowState(row, { ...info, unlocked: true }, info.isMax);
        const costEl = row.querySelector('.row-cost');
        costEl.textContent = info.isMax
          ? this.i18n.t('max_level')
          : format(info.batchCost, lang);
        costEl.classList.toggle('cant', !info.affordable);
        const lvlEl = row.querySelector('.row-owned');
        lvlEl.textContent = `${this.i18n.t('level')}: ${format(info.level, lang)}`;
      }
    }
  }

  /** Hide the tap hint after the first successful tap. */
  onFirstTap() {
    if (this._firstTapDone) return;
    this._firstTapDone = true;
    this.el.tapHint.classList.add('hidden');
  }

  /** Spawn a floating "+N" via the particle system at screen coords. */
  // (Floating text is handled directly by ParticleSystem from main.js.)
}
