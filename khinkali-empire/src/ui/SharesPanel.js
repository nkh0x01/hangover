// SharesPanel.js
// Tab B — the shares / investment meta UI. Live valuation, equity sold, owner
// share + passive penalty, one card per investor (raise rounds with correct
// lump sums, cooldown countdowns, locked states) and a buyback control.

import { investors } from '../data/investors.js';
import { EQUITY_CAP } from '../core/SharesSystem.js';

export class SharesPanel {
  constructor(game, i18n) {
    this.game = game;
    this.i18n = i18n;
    this.economy = game.economy;
    this.shares = game.shares;
    this.root = document.getElementById('tab-shares');
    this.cards = {};
  }

  init() {
    this._build();
    return this;
  }

  _build() {
    const t = this.i18n.t.bind(this.i18n);
    this.root.innerHTML = '';

    // Header.
    const head = document.createElement('div');
    head.className = 'shares-head';
    head.innerHTML = `
      <h3 data-i18n="shares_title">${t('shares_title')}</h3>
      <div class="shares-grid">
        <span class="lbl" data-i18n="valuation">${t('valuation')}</span>
        <span class="val gold" data-f="valuation">0</span>
        <span class="lbl" data-i18n="equity_sold">${t('equity_sold')}</span>
        <span class="val" data-f="equitySold">0%</span>
        <span class="lbl" data-i18n="owner_share">${t('owner_share')}</span>
        <span class="val" data-f="ownerShare">100%</span>
        <span class="lbl" data-i18n="passive_mult">${t('passive_mult')}</span>
        <span class="val" data-f="passiveMult">×1.00</span>
      </div>
      <div class="equity-bar"><i data-f="equityBar"></i></div>`;
    this.root.appendChild(head);
    this.head = head;

    // Investor cards.
    investors.forEach((inv) => {
      const card = document.createElement('div');
      card.className = 'investor-card';
      card.innerHTML = `
        <div class="inv-top">
          <span class="inv-name" data-i18n="${inv.nameKey}">${t(inv.nameKey)}</span>
          <span class="inv-equity">−${(inv.equityPerRound * 100).toFixed(0)}%</span>
        </div>
        <div class="inv-terms"></div>
        <div class="inv-action">
          <button class="raise-btn" data-i18n="raise_round">${t('raise_round')}</button>
        </div>`;
      const btn = card.querySelector('.raise-btn');
      btn.addEventListener('click', () => this._raise(inv));
      this.root.appendChild(card);
      this.cards[inv.id] = { card, terms: card.querySelector('.inv-terms'), btn };
    });

    // Buyback card.
    const bb = document.createElement('div');
    bb.className = 'buyback-card';
    bb.innerHTML = `
      <div class="bb-title" data-i18n="buyback">${t('buyback')}</div>
      <div class="bb-sub" data-f="bbSub"></div>
      <button class="buyback-btn"></button>`;
    this.bbBtn = bb.querySelector('.buyback-btn');
    this.bbSub = bb.querySelector('[data-f="bbSub"]');
    this.bbBtn.addEventListener('click', () => this._buyback());
    this.root.appendChild(bb);
    this.bbCard = bb;

    this.refresh();
  }

  _raise(inv) {
    const res = this.game.raise(inv);
    if (res.ok) {
      if (this.onRaise) this.onRaise(res.lumpSum);
    } else if (this.onBlocked) {
      this.onBlocked(res.reason, res.params);
    }
    this.refresh();
  }

  _buyback() {
    const res = this.game.buyback();
    if (res.ok) {
      if (this.onBuyback) this.onBuyback(res.chunk);
    } else if (this.onBlocked) {
      this.onBlocked(res.reason, res.params);
    }
    this.refresh();
  }

  refresh() {
    const t = this.i18n.t.bind(this.i18n);
    const locale = this.i18n.getLanguage();
    const fmt = (n) => this.economy.format(n, locale);
    const s = this.game.state;
    const now = Date.now();

    const valuation = this.shares.valuation(s);
    const equitySold = s.shares.equitySold || 0;
    const ownerShare = this.shares.ownerShare(s);

    const setF = (key, val) => {
      const el = this.head.querySelector(`[data-f="${key}"]`);
      if (el) el.textContent = val;
    };
    setF('valuation', '🪙 ' + fmt(valuation));
    setF('equitySold', (equitySold * 100).toFixed(0) + '%');
    setF('ownerShare', (ownerShare * 100).toFixed(0) + '%');
    setF('passiveMult', '×' + ownerShare.toFixed(2));
    const bar = this.head.querySelector('[data-f="equityBar"]');
    if (bar) bar.style.width = ((equitySold / EQUITY_CAP) * 100).toFixed(0) + '%';

    // Investor cards.
    investors.forEach((inv) => {
      const c = this.cards[inv.id];
      const lump = this.shares.lumpSumFor(inv, valuation);
      const check = this.shares.canRaise(s, inv, now);
      const locked = valuation < inv.minValuation;
      c.card.classList.toggle('locked', locked);

      let terms;
      if (locked) {
        terms = `🔒 ${t('min_valuation', { value: fmt(inv.minValuation) })}`;
      } else {
        terms = `${t('lump_sum')}: <b>🪙 ${fmt(lump)}</b> · ${t('cooldown')} ${inv.cooldownSec}s`;
      }
      c.terms.innerHTML = terms;

      c.btn.disabled = !check.ok;
      if (check.ok) {
        c.btn.textContent = t('raise_round');
      } else if (check.reason === 'cooldown_wait') {
        c.btn.textContent = t('cooldown_wait', check.params);
      } else if (check.reason === 'equity_cap_reached') {
        c.btn.textContent = t('equity_cap_reached');
      } else if (check.reason === 'min_valuation') {
        c.btn.textContent = t('locked');
      } else {
        c.btn.textContent = t('raise_round');
      }
    });

    // Buyback.
    const chunk = this.shares.nextBuybackChunk(s);
    const bbCheck = this.shares.canBuyback(s);
    if (chunk <= 0) {
      this.bbSub.textContent = t('buyback_none');
      this.bbBtn.textContent = t('buyback');
      this.bbBtn.disabled = true;
    } else {
      const cost = this.shares.buybackCost(valuation, chunk);
      this.bbSub.innerHTML = `${t('buyback_chunk', { value: (chunk * 100).toFixed(0) })} · <b>🪙 ${fmt(cost)}</b>`;
      this.bbBtn.textContent = `🪙 ${fmt(cost)}`;
      this.bbBtn.disabled = !bbCheck.ok;
    }
  }

  relocalize() {
    this.i18n.applyBindings(this.root);
    this.refresh();
  }
}

export default SharesPanel;
