// SteamSystem.js
// Additive soft-particle steam rising from the tower chimney. Intensity
// (emission count + rise speed + opacity) scales with current autoKPS.
// Particles are pooled — no per-frame allocation in steady state.

import * as THREE from 'three';

const POOL_SIZE = 64;

export class SteamSystem {
  constructor(scene) {
    this.scene = scene;
    this.group = new THREE.Group();
    this.scene.add(this.group);
    this.intensity = 0; // 0..1
    this._emitAccum = 0;
    this._origin = new THREE.Vector3(0, 13.6, 0);

    const tex = this._makeSoftTexture();
    this.material = new THREE.SpriteMaterial({
      map: tex,
      color: 0xf4ead8,
      transparent: true,
      opacity: 1,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
    });

    this.pool = [];
    for (let i = 0; i < POOL_SIZE; i++) {
      const sprite = new THREE.Sprite(this.material.clone());
      sprite.visible = false;
      sprite.userData = { active: false, life: 0, maxLife: 1, vx: 0, vy: 0, vz: 0, baseScale: 1 };
      this.group.add(sprite);
      this.pool.push(sprite);
    }
  }

  _makeSoftTexture() {
    const c = document.createElement('canvas');
    c.width = 64;
    c.height = 64;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, 'rgba(255,255,255,0.9)');
    g.addColorStop(0.4, 'rgba(255,255,255,0.4)');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 64, 64);
    const tex = new THREE.CanvasTexture(c);
    return tex;
  }

  setOrigin(vec3) {
    this._origin.copy(vec3);
  }

  /** Set steam strength from a normalized 0..1 production intensity. */
  setIntensity(v) {
    this.intensity = Math.max(0, Math.min(1, v));
  }

  _spawnOne() {
    const p = this.pool.find((s) => !s.userData.active);
    if (!p) return;
    const ud = p.userData;
    ud.active = true;
    ud.life = 0;
    ud.maxLife = 2.4 + Math.random() * 1.6;
    const spread = 0.18 + this.intensity * 0.22;
    p.position.set(
      this._origin.x + (Math.random() - 0.5) * spread,
      this._origin.y,
      this._origin.z + (Math.random() - 0.5) * spread
    );
    ud.vx = (Math.random() - 0.5) * 0.25;
    ud.vz = (Math.random() - 0.5) * 0.25;
    ud.vy = 0.5 + this.intensity * 1.8 + Math.random() * 0.3;
    ud.baseScale = 0.5 + Math.random() * 0.4;
    p.visible = true;
    p.scale.setScalar(ud.baseScale);
    p.material.opacity = 0;
  }

  update(dt) {
    // Emission: idle = faint wisp, high production = thick billows.
    // Rate scales from ~1.5/s (idle) up to ~26/s at full intensity.
    const rate = 1.5 + this.intensity * 24;
    this._emitAccum += dt * rate;
    while (this._emitAccum >= 1) {
      this._emitAccum -= 1;
      this._spawnOne();
    }

    const maxOpacity = 0.18 + this.intensity * 0.55;
    for (const p of this.pool) {
      const ud = p.userData;
      if (!ud.active) continue;
      ud.life += dt;
      const t = ud.life / ud.maxLife;
      if (t >= 1) {
        ud.active = false;
        p.visible = false;
        continue;
      }
      p.position.x += ud.vx * dt;
      p.position.y += ud.vy * dt;
      p.position.z += ud.vz * dt;
      // Grow as it rises, fade in then out.
      const scale = ud.baseScale * (1 + t * 1.8);
      p.scale.setScalar(scale);
      const fade = t < 0.25 ? t / 0.25 : 1 - (t - 0.25) / 0.75;
      p.material.opacity = maxOpacity * Math.max(0, fade);
    }
  }
}

export default SteamSystem;
