/**
 * ParticleSystem
 * --------------
 * Tap feedback FX with object pooling (no per-tap allocation in steady state):
 *  - 3D khinkali meshes that pop outward, arc up under gravity, scale down & fade.
 *  - Floating "+N" gold text as pooled DOM elements that rise and fade.
 * Golden taps get a bigger, gold-tinted treatment.
 */

import * as THREE from 'three';

const POOL_SIZE = 36; // max simultaneous 3D khinkali bits
const FLOAT_POOL = 24; // max simultaneous DOM "+N" labels
const GRAVITY = -9.0;

export class ParticleSystem {
  /**
   * @param {THREE.Scene} scene
   * @param {THREE.Camera} camera - for projecting 3D → screen if needed
   * @param {HTMLElement} floatLayer - DOM container for "+N" labels
   */
  constructor(scene, camera, floatLayer) {
    this.scene = scene;
    this.camera = camera;
    this.floatLayer = floatLayer;
    // The 3D khinkali burst needs a WebGL scene; the DOM "+N" floats do not.
    // When no scene is available (WebGL unsupported) we run in floats-only mode.
    this.has3D = !!scene;
    if (this.has3D) this._buildMeshPool();
    this._buildFloatPool();
  }

  _buildMeshPool() {
    // Shared small khinkali geometry (a pinched dumpling-ish blob).
    const geo = new THREE.SphereGeometry(0.12, 8, 8);
    geo.scale(1, 0.85, 1);
    const matNormal = new THREE.MeshStandardMaterial({
      color: 0xf4e6c8,
      roughness: 0.6,
      metalness: 0.0,
    });
    const matGold = new THREE.MeshStandardMaterial({
      color: 0xffd24a,
      emissive: 0xffaa20,
      emissiveIntensity: 0.7,
      metalness: 0.8,
      roughness: 0.3,
    });
    this.matNormal = matNormal;
    this.matGold = matGold;

    this.pool = [];
    for (let i = 0; i < POOL_SIZE; i++) {
      const mesh = new THREE.Mesh(geo, matNormal);
      mesh.visible = false;
      mesh.userData.active = false;
      this.scene.add(mesh);
      this.pool.push(mesh);
    }
  }

  _buildFloatPool() {
    this.floatPool = [];
    for (let i = 0; i < FLOAT_POOL; i++) {
      const el = document.createElement('div');
      el.className = 'float-pop';
      el.style.opacity = '0';
      el.style.display = 'none';
      this.floatLayer.appendChild(el);
      this.floatPool.push({ el, active: false, life: 0, maxLife: 1, x: 0, y: 0 });
    }
  }

  _getMesh() {
    for (const m of this.pool) {
      if (!m.userData.active) return m;
    }
    return null; // pool exhausted; drop the spawn rather than allocate
  }

  /**
   * Spawn a tap burst at a 3D world point.
   * @param {THREE.Vector3} point
   * @param {boolean} golden
   */
  spawnTapBurst(point, golden = false) {
    if (!this.has3D || !point) return; // floats-only mode has no 3D burst
    const count = golden ? 6 : 1 + Math.floor(Math.random() * 3); // 1–3 (6 if golden)
    for (let i = 0; i < count; i++) {
      const m = this._getMesh();
      if (!m) break;
      m.visible = true;
      m.userData.active = true;
      m.material = golden ? this.matGold : this.matNormal;
      m.position.copy(point);
      const baseScale = golden ? 1.5 : 1.0;
      m.scale.set(baseScale, baseScale, baseScale);
      // Outward + upward initial velocity.
      const ang = Math.random() * Math.PI * 2;
      const spread = golden ? 2.6 : 1.8;
      m.userData.vx = Math.cos(ang) * spread * (0.4 + Math.random());
      m.userData.vy = 3.0 + Math.random() * 2.0;
      m.userData.vz = Math.sin(ang) * spread * (0.4 + Math.random());
      m.userData.life = 0;
      m.userData.maxLife = 0.9 + Math.random() * 0.4;
      m.userData.baseScale = baseScale;
      m.userData.spin = (Math.random() - 0.5) * 12;
    }
  }

  /**
   * Spawn a floating "+N" label at a screen position (clientX/clientY).
   */
  spawnFloatingText(screenX, screenY, text, golden = false) {
    const f = this.floatPool.find((x) => !x.active);
    if (!f) return;
    f.active = true;
    f.life = 0;
    f.maxLife = golden ? 1.4 : 1.0;
    // Position relative to the float layer's box.
    const rect = this.floatLayer.getBoundingClientRect();
    f.x = screenX - rect.left + (Math.random() - 0.5) * 24;
    f.y = screenY - rect.top;
    f.el.textContent = text;
    f.el.className = golden ? 'float-pop golden' : 'float-pop';
    f.el.style.display = 'block';
    f.el.style.left = f.x + 'px';
    f.el.style.top = f.y + 'px';
    f.el.style.opacity = '1';
    f.el.style.transform = 'translate(-50%, -50%) scale(1)';
  }

  update(dt) {
    // ---- 3D khinkali bits ----
    if (this.has3D)
    for (const m of this.pool) {
      if (!m.userData.active) continue;
      const u = m.userData;
      u.life += dt;
      if (u.life >= u.maxLife) {
        u.active = false;
        m.visible = false;
        continue;
      }
      u.vy += GRAVITY * dt;
      m.position.x += u.vx * dt;
      m.position.y += u.vy * dt;
      m.position.z += u.vz * dt;
      m.rotation.x += u.spin * dt;
      m.rotation.y += u.spin * 0.7 * dt;
      const t = u.life / u.maxLife;
      const s = u.baseScale * (1 - t * 0.8); // shrink as it fades
      m.scale.set(s, s, s);
    }

    // ---- DOM "+N" labels ----
    for (const f of this.floatPool) {
      if (!f.active) continue;
      f.life += dt;
      const t = f.life / f.maxLife;
      if (t >= 1) {
        f.active = false;
        f.el.style.display = 'none';
        f.el.style.opacity = '0';
        continue;
      }
      const rise = 70 * t; // pixels upward over its life
      const scale = 1 + (f.life < 0.12 ? f.life * 1.5 : 0.18) - t * 0.1;
      f.el.style.transform = `translate(-50%, -50%) translateY(${-rise}px) scale(${scale})`;
      f.el.style.opacity = String(1 - Math.max(0, t - 0.5) * 2);
    }
  }
}
