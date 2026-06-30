// UIManager.js
// HUD updates, modal system (settings / language / welcome-back / confirm),
// toasts, and per-frame UI refresh wiring for prep table, shop and shares.

import { PrepTable } from './PrepTable.js';
import { ShopSheet } from './ShopSheet.js';
import { SharesPanel } from './SharesPanel.js';

const UI_REFRESH_HZ = 10; // throttle DOM-heavy refresh

export class UIManager {
  constructor(game, i18n, input, particles) {
    this.game = game;
    this.i18n = i18n;
    this.input = input;
    this.particles = particles;
    this.economy = game.economy;

    this.hudGold = document.getElementById('hud-gold-value');
    this.hudKps = document.getElementById('hud-kps-value');
    this.handleGold = document.getElementById('handle-gold-value');
    this.modalRoot = document.getElementById('modal-root');

    this._refreshAccum = 0;
    this._toastTimer = null;
  }

  init() {
    // Components.
    this.prep = new PrepTable(this.game, this.i18n, this.input, this.particles).init();
    this.shop = new ShopSheet(this.game, this.i18n, this.input).init();
    this.sharesPanel = new SharesPanel(this.game, this.i18n).init();
    this.shop.setSharesPanel(this.sharesPanel);

    // Cross-component callbacks.
    this.shop.onCannotAfford = () => this.toast(this.i18n.t('cannot_afford'));
    this.prep.onGolden = () => this.toast(this.i18n.t('golden_khinkali'));
    this.sharesPanel.onRaise = (lump) => this.toast(this.i18n.t('raise_success', { value: this.economy.format(lump, this.i18n.getLanguage()) }));
    this.sharesPanel.onBuyback = (chunk) => this.toast(this.i18n.t('buyback_success', { value: (chunk * 100).toFixed(0) }));
    this.sharesPanel.onBlocked = (reason, params) => {
      if (reason) this.toast(this.i18n.t(reason, params));
    };

    // Settings button.
    document.getElementById('settings-btn').addEventListener('click', () => this.openSettings());

    // Toast element.
    this.toastEl = document.createElement('div');
    this.toastEl.id = 'toast';
    document.body.appendChild(this.toastEl);

    // Re-localize components live on language change.
    this.i18n.onChange(() => {
      this.prep.relocalize();
      this.shop.relocalize();
      this.sharesPanel.relocalize();
    });

    // Initial localized binding pass over the static DOM.
    this.i18n.applyBindings(document);

    // Welcome-back modal if offline earnings were granted.
    if (this.game.offlineReport) this.showWelcomeBack(this.game.offlineReport);

    return this;
  }

  // ---- per-frame ---------------------------------------------------------
  update(dt) {
    const locale = this.i18n.getLanguage();
    // Smooth gold counter every frame.
    const goldStr = this.economy.format(this.game.state.gold, locale);
    this.hudGold.textContent = goldStr;
    this.handleGold.textContent = goldStr;
    this.hudKps.textContent = this.economy.formatRate(this.game.cache.autoKPS, locale);

    // Throttle the heavier component refreshes.
    this._refreshAccum += dt;
    if (this._refreshAccum >= 1 / UI_REFRESH_HZ) {
      this._refreshAccum = 0;
      this.prep.refresh();
      this.shop.refresh();
      const sharesActive = document.getElementById('tab-shares').classList.contains('active');
      if (sharesActive) this.sharesPanel.refresh();
    }
  }

  // ---- modals ------------------------------------------------------------
  _openModal(buildInner) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    const modal = document.createElement('div');
    modal.className = 'modal';
    backdrop.appendChild(modal);
    const close = () => {
      if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
    };
    backdrop.addEventListener('pointerdown', (e) => {
      if (e.target === backdrop) close();
    });
    buildInner(modal, close);
    this.modalRoot.appendChild(backdrop);
    return close;
  }

  openSettings() {
    const t = this.i18n.t.bind(this.i18n);
    this._openModal((modal, close) => {
      const h = document.createElement('h2');
      h.textContent = t('settings');
      modal.appendChild(h);

      // Language section.
      const langLabel = document.createElement('div');
      langLabel.className = 'settings-row';
      langLabel.textContent = t('language');
      modal.appendChild(langLabel);

      const list = document.createElement('div');
      list.className = 'lang-list';
      this.i18n.available().forEach((lang) => {
        const b = document.createElement('button');
        b.className = 'lang-btn' + (lang.code === this.i18n.getLanguage() ? ' active' : '');
        b.textContent = lang.name;
        b.addEventListener('click', () => {
          this.i18n.setLanguage(lang.code);
          list.querySelectorAll('.lang-btn').forEach((x) => x.classList.remove('active'));
          b.classList.add('active');
          h.textContent = this.i18n.t('settings');
          langLabel.textContent = this.i18n.t('language');
          resetBtn.textContent = this.i18n.t('reset_save');
          closeBtn.textContent = this.i18n.t('close');
        });
        list.appendChild(b);
      });
      modal.appendChild(list);

      // Actions.
      const actions = document.createElement('div');
      actions.className = 'modal-actions';
      const resetBtn = document.createElement('button');
      resetBtn.className = 'modal-btn danger';
      resetBtn.textContent = t('reset_save');
      resetBtn.addEventListener('click', () => {
        close();
        this.confirmReset();
      });
      const closeBtn = document.createElement('button');
      closeBtn.className = 'modal-btn primary';
      closeBtn.textContent = t('close');
      closeBtn.addEventListener('click', close);
      actions.appendChild(resetBtn);
      actions.appendChild(closeBtn);
      modal.appendChild(actions);
    });
  }

  confirmReset() {
    const t = this.i18n.t.bind(this.i18n);
    this._openModal((modal, close) => {
      const h = document.createElement('h2');
      h.textContent = t('reset_save');
      const p = document.createElement('p');
      p.textContent = t('confirm_reset');
      const actions = document.createElement('div');
      actions.className = 'modal-actions';
      const no = document.createElement('button');
      no.className = 'modal-btn';
      no.textContent = t('no');
      no.addEventListener('click', close);
      const yes = document.createElement('button');
      yes.className = 'modal-btn danger';
      yes.textContent = t('yes');
      yes.addEventListener('click', () => {
        this.game.reset();
        this.prep.refresh();
        this.shop.revealed.clear();
        this.shop.refresh();
        this.sharesPanel.refresh();
        close();
        this.toast(t('reset_done'));
      });
      actions.appendChild(no);
      actions.appendChild(yes);
      modal.appendChild(h);
      modal.appendChild(p);
      modal.appendChild(actions);
    });
  }

  showWelcomeBack(report) {
    const t = this.i18n.t.bind(this.i18n);
    const locale = this.i18n.getLanguage();
    this._openModal((modal, close) => {
      const h = document.createElement('h2');
      h.textContent = t('welcome_back');
      const big = document.createElement('div');
      big.className = 'modal-big';
      big.textContent = '🪙 ' + this.economy.format(report.earned, locale);
      const p = document.createElement('p');
      p.textContent = t('offline_earned', { value: this.economy.format(report.earned, locale) });
      const actions = document.createElement('div');
      actions.className = 'modal-actions';
      const ok = document.createElement('button');
      ok.className = 'modal-btn primary';
      ok.textContent = t('collect');
      ok.addEventListener('click', close);
      actions.appendChild(ok);
      modal.appendChild(h);
      modal.appendChild(big);
      modal.appendChild(p);
      modal.appendChild(actions);
    });
  }

  // ---- toast -------------------------------------------------------------
  toast(msg) {
    this.toastEl.textContent = msg;
    this.toastEl.classList.add('show');
    if (this._toastTimer) clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(() => this.toastEl.classList.remove('show'), 1800);
  }
}

export default UIManager;
