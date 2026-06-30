// Tween.js
// Tiny tween/easing helper. Works for both Three.js objects and DOM nodes.
// No external deps. A single runner is updated from the main loop.

export function lerp(a, b, t) {
  return a + (b - a) * t;
}

export const Easings = {
  linear: (t) => t,
  easeOutCubic: (t) => 1 - Math.pow(1 - t, 3),
  easeInOutQuad: (t) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2),
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

class TweenInstance {
  constructor(opts) {
    this.duration = opts.duration || 0.3;
    this.elapsed = 0;
    this.ease = opts.ease || Easings.easeOutCubic;
    this.onUpdate = opts.onUpdate || null;
    this.onComplete = opts.onComplete || null;
    this.delay = opts.delay || 0;
    this.done = false;
  }
  update(dt) {
    if (this.done) return;
    if (this.delay > 0) {
      this.delay -= dt;
      if (this.delay > 0) return;
      dt = -this.delay;
      this.delay = 0;
    }
    this.elapsed += dt;
    let t = Math.min(1, this.elapsed / this.duration);
    const e = this.ease(t);
    if (this.onUpdate) this.onUpdate(e, t);
    if (t >= 1) {
      this.done = true;
      if (this.onComplete) this.onComplete();
    }
  }
}

class TweenRunner {
  constructor() {
    this.tweens = [];
  }
  add(opts) {
    const tw = new TweenInstance(opts);
    this.tweens.push(tw);
    return tw;
  }
  update(dt) {
    for (let i = this.tweens.length - 1; i >= 0; i--) {
      const tw = this.tweens[i];
      tw.update(dt);
      if (tw.done) this.tweens.splice(i, 1);
    }
  }
  clear() {
    this.tweens.length = 0;
  }
}

export const tweenRunner = new TweenRunner();

/**
 * Squish-stretch bounce. Works on:
 *  - Three.js Object3D (has .scale.set) using its baseScale, or
 *  - a DOM element (sets transform scale around its current transform).
 * intensity ~ 0.2 .. 0.6 looks good.
 */
export function squishStretch(target, intensity = 0.35, baseScale = 1) {
  const isObject3D = !!(target && target.scale && typeof target.scale.set === 'function');
  if (isObject3D) {
    tweenRunner.add({
      duration: 0.42,
      ease: Easings.easeOutElastic,
      onUpdate: (e) => {
        // Pop bigger then settle; squash on x/z while stretching y a touch.
        const pop = 1 + intensity * (1 - e);
        const squashY = 1 + intensity * 0.6 * (1 - e);
        target.scale.set(baseScale * pop, baseScale * squashY, baseScale * pop);
      },
      onComplete: () => target.scale.set(baseScale, baseScale, baseScale),
    });
  } else if (target && target.style) {
    const el = target;
    tweenRunner.add({
      duration: 0.4,
      ease: Easings.easeOutElastic,
      onUpdate: (e) => {
        const sx = 1 + intensity * (1 - e);
        const sy = 1 - intensity * 0.55 * (1 - e);
        el.style.transform = `scale(${sx.toFixed(3)}, ${sy.toFixed(3)})`;
      },
      onComplete: () => {
        el.style.transform = '';
      },
    });
  }
}

export default {
  lerp,
  Easings,
  tweenRunner,
  squishStretch,
};
