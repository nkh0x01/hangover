/**
 * Tween.js
 * --------
 * A tiny dependency-free tween system: lerp, easing functions, a tween runner
 * driven from the game loop, and a `squishStretch` juice helper for Object3D.
 */

// ----------------------------- math helpers -----------------------------
export function lerp(a, b, t) {
  return a + (b - a) * t;
}

export function clamp01(t) {
  return t < 0 ? 0 : t > 1 ? 1 : t;
}

// ------------------------------- easings --------------------------------
export const Easing = {
  linear: (t) => t,
  easeOutCubic: (t) => 1 - Math.pow(1 - t, 3),
  easeInOutQuad: (t) =>
    t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2,
  easeOutBack: (t) => {
    const c1 = 1.70158;
    const c3 = c1 + 1;
    return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
  },
  easeOutElastic: (t) => {
    const c4 = (2 * Math.PI) / 3;
    if (t === 0) return 0;
    if (t === 1) return 1;
    return Math.pow(2, -10 * t) * Math.sin((t * 10 - 0.75) * c4) + 1;
  },
};

/**
 * A single tween instance. Each frame `update(dt)` advances its progress and
 * invokes onUpdate with the eased 0..1 value; onComplete fires once at the end.
 */
class Tween {
  constructor({ duration, easing, onUpdate, onComplete }) {
    this.duration = Math.max(0.0001, duration);
    this.easing = easing || Easing.linear;
    this.onUpdate = onUpdate;
    this.onComplete = onComplete;
    this.elapsed = 0;
    this.done = false;
  }

  update(dt) {
    if (this.done) return;
    this.elapsed += dt;
    const t = clamp01(this.elapsed / this.duration);
    const eased = this.easing(t);
    if (this.onUpdate) this.onUpdate(eased, t);
    if (t >= 1) {
      this.done = true;
      if (this.onComplete) this.onComplete();
    }
  }
}

/**
 * Manages a set of active tweens. Add tweens with `add(config)`; call
 * `update(dt)` once per frame from the main loop.
 */
export class TweenManager {
  constructor() {
    this.tweens = [];
  }

  add(config) {
    const t = new Tween(config);
    this.tweens.push(t);
    return t;
  }

  update(dt) {
    for (let i = this.tweens.length - 1; i >= 0; i--) {
      const t = this.tweens[i];
      t.update(dt);
      if (t.done) this.tweens.splice(i, 1);
    }
  }

  clear() {
    this.tweens.length = 0;
  }
}

/**
 * Squish-stretch juice: briefly squashes an Object3D on one axis and stretches
 * the others, then springs back to its base scale using easeOutElastic.
 * @param {THREE.Object3D} object3D
 * @param {TweenManager} manager
 * @param {number} intensity - 0..1, how strong the squish is.
 */
export function squishStretch(object3D, manager, intensity = 0.35) {
  // Capture the resting scale once so repeated taps don't drift.
  if (!object3D.userData._baseScale) {
    object3D.userData._baseScale = object3D.scale.clone();
  }
  const base = object3D.userData._baseScale;
  const squish = 1 - intensity;
  const stretch = 1 + intensity * 0.6;

  manager.add({
    duration: 0.42,
    easing: Easing.easeOutElastic,
    onUpdate: (e) => {
      // At e=0 we are fully squished, springing to 1 (= base) by e=1.
      const sx = lerp(stretch, 1, e);
      const sy = lerp(squish, 1, e);
      object3D.scale.set(base.x * sx, base.y * sy, base.z * sx);
    },
    onComplete: () => {
      object3D.scale.copy(base);
    },
  });
}

/**
 * A small one-shot bounce on an object's scale (used for the tower recoil).
 */
export function bounce(object3D, manager, intensity = 0.08) {
  if (!object3D.userData._baseScaleB) {
    object3D.userData._baseScaleB = object3D.scale.clone();
  }
  const base = object3D.userData._baseScaleB;
  manager.add({
    duration: 0.5,
    easing: Easing.easeOutElastic,
    onUpdate: (e) => {
      const s = lerp(1 - intensity, 1, e);
      object3D.scale.set(base.x * s, base.y * (1 + (1 - s)), base.z * s);
    },
    onComplete: () => object3D.scale.copy(base),
  });
}
