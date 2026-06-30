// ParticleSystem.js
// Pooled DOM particles: emoji "pops" with gravity arcs + scale/fade, and
// floating "+N" text that rises and fades. Pooled so no allocation churn.

const POP_POOL = 48;
const TEXT_POOL = 28;

export class ParticleSystem {
  constructor() {
    this.layer = document.createElement('div');
    this.layer.id = 'fx-layer';
    Object.assign(this.layer.style, {
      position: 'fixed',
      inset: '0',
      pointerEvents: 'none',
      zIndex: '60',
      overflow: 'hidden',
    });
    document.body.appendChild(this.layer);

    this.pops = [];
    this.texts = [];
    this._initPools();
  }

  _initPools() {
    for (let i = 0; i < POP_POOL; i++) {
      const el = document.createElement('div');
      el.className = 'fx-pop';
      Object.assign(el.style, {
        position: 'fixed',
        left: '0',
        top: '0',
        fontSize: '22px',
        willChange: 'transform, opacity',
        opacity: '0',
        pointerEvents: 'none',
        userSelect: 'none',
      });
      this.layer.appendChild(el);
      this.pops.push({ el, active: false, x: 0, y: 0, vx: 0, vy: 0, life: 0, maxLife: 1, rot: 0, vr: 0 });
    }
    for (let i = 0; i < TEXT_POOL; i++) {
      const el = document.createElement('div');
      el.className = 'float-text';
      el.style.opacity = '0';
      this.layer.appendChild(el);
      this.texts.push({ el, active: false, x: 0, y: 0, vy: 0, life: 0, maxLife: 1 });
    }
  }

  _freePop() {
    return this.pops.find((p) => !p.active);
  }
  _freeText() {
    return this.texts.find((t) => !t.active);
  }

  /** Spawn `count` emoji pops at screen coords with gravity arcs. */
  spawnPop(x, y, emoji, count = 3) {
    for (let i = 0; i < count; i++) {
      const p = this._freePop();
      if (!p) return;
      p.active = true;
      p.el.textContent = emoji;
      p.x = x;
      p.y = y;
      const angle = -Math.PI / 2 + (Math.random() - 0.5) * 1.6;
      const speed = 120 + Math.random() * 160;
      p.vx = Math.cos(angle) * speed;
      p.vy = Math.sin(angle) * speed;
      p.rot = 0;
      p.vr = (Math.random() - 0.5) * 6;
      p.life = 0;
      p.maxLife = 0.7 + Math.random() * 0.4;
      p.el.style.opacity = '1';
      p.el.style.transform = `translate(${x}px, ${y}px)`;
    }
  }

  /** Spawn a floating "+N" text that rises and fades. */
  spawnText(x, y, text, variant = '') {
    const t = this._freeText();
    if (!t) return;
    t.active = true;
    t.el.textContent = text;
    t.el.className = 'float-text' + (variant ? ' ' + variant : '');
    t.x = x;
    t.y = y;
    t.vy = -70 - Math.random() * 30;
    t.life = 0;
    t.maxLife = 1.0;
    t.el.style.opacity = '1';
    t.el.style.transform = `translate(-50%, 0) translate(${x}px, ${y}px)`;
  }

  update(dt) {
    const g = 520; // gravity px/s^2
    for (const p of this.pops) {
      if (!p.active) continue;
      p.life += dt;
      const t = p.life / p.maxLife;
      if (t >= 1) {
        p.active = false;
        p.el.style.opacity = '0';
        continue;
      }
      p.vy += g * dt;
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      p.rot += p.vr * dt;
      const scale = 1 - t * 0.5;
      p.el.style.transform = `translate(${p.x.toFixed(1)}px, ${p.y.toFixed(1)}px) rotate(${p.rot.toFixed(2)}rad) scale(${scale.toFixed(2)})`;
      p.el.style.opacity = (1 - t).toFixed(2);
    }
    for (const tx of this.texts) {
      if (!tx.active) continue;
      tx.life += dt;
      const t = tx.life / tx.maxLife;
      if (t >= 1) {
        tx.active = false;
        tx.el.style.opacity = '0';
        continue;
      }
      tx.y += tx.vy * dt;
      const scale = 1 + t * 0.25;
      tx.el.style.transform = `translate(-50%, 0) translate(${tx.x.toFixed(1)}px, ${tx.y.toFixed(1)}px) scale(${scale.toFixed(2)})`;
      tx.el.style.opacity = (1 - t * t).toFixed(2);
    }
  }
}

export default ParticleSystem;
