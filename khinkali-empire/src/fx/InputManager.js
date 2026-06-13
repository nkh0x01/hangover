/**
 * InputManager
 * ------------
 * Mobile-correct pointer/touch handling with raycasting against the hero mesh.
 * - Uses Pointer Events so mouse + touch share one path.
 * - A "tap" requires small movement (<12px) and short duration (<350ms), so
 *   dragging the bottom sheet never triggers a tap.
 * - NDC is computed from the canvas bounding rect (not window size) for
 *   correctness with safe areas and the centered phone column.
 * - Multi-touch: each changed touch is processed independently.
 * - Touches starting on DOM UI are ignored (the UI handles those itself).
 */

import * as THREE from 'three';

const MOVE_THRESHOLD = 12; // px
const TIME_THRESHOLD = 350; // ms

export class InputManager {
  /**
   * @param {HTMLCanvasElement} canvas
   * @param {THREE.Camera} camera
   * @param {THREE.Object3D} heroMesh - the tappable hero (and children)
   * @param {(info:{point:THREE.Vector3, clientX:number, clientY:number})=>void} onTap
   */
  constructor(canvas, camera, heroMesh, onTap) {
    this.canvas = canvas;
    this.camera = camera;
    this.hero = heroMesh;
    this.onTap = onTap;
    this.raycaster = new THREE.Raycaster();
    this.ndc = new THREE.Vector2();
    this._pointers = new Map(); // pointerId -> {x,y,t}
  }

  attach() {
    // Pointer Events cover mouse, pen and touch.
    this.canvas.addEventListener('pointerdown', this._onDown, { passive: true });
    this.canvas.addEventListener('pointerup', this._onUp, { passive: true });
    this.canvas.addEventListener('pointercancel', this._onCancel, {
      passive: true,
    });

    // Prevent the canvas from scrolling/zooming the page on touch. We attach a
    // non-passive touch handler ONLY for preventDefault; tap logic stays in
    // pointer events above. Multi-touch taps are also surfaced here so rapid
    // two-finger tapping registers multiple hits.
    this.canvas.addEventListener('touchstart', this._onTouchStart, {
      passive: false,
    });
    this.canvas.addEventListener('touchmove', this._onTouchMove, {
      passive: false,
    });
  }

  detach() {
    this.canvas.removeEventListener('pointerdown', this._onDown);
    this.canvas.removeEventListener('pointerup', this._onUp);
    this.canvas.removeEventListener('pointercancel', this._onCancel);
    this.canvas.removeEventListener('touchstart', this._onTouchStart);
    this.canvas.removeEventListener('touchmove', this._onTouchMove);
  }

  // --- Pointer (single mouse / primary touch) path ---
  _onDown = (e) => {
    this._pointers.set(e.pointerId, {
      x: e.clientX,
      y: e.clientY,
      t: performance.now(),
    });
  };

  _onUp = (e) => {
    const start = this._pointers.get(e.pointerId);
    this._pointers.delete(e.pointerId);
    if (!start) return;
    const dx = e.clientX - start.x;
    const dy = e.clientY - start.y;
    const dist = Math.hypot(dx, dy);
    const dt = performance.now() - start.t;
    if (dist <= MOVE_THRESHOLD && dt <= TIME_THRESHOLD) {
      // pointer events already fire once per touch on most browsers; only
      // process here for mouse/pen to avoid double-counting touches handled
      // by the touch path below.
      if (e.pointerType === 'mouse' || e.pointerType === 'pen') {
        this._tryTap(e.clientX, e.clientY);
      }
    }
  };

  _onCancel = (e) => {
    this._pointers.delete(e.pointerId);
  };

  // --- Touch path (handles multi-touch + preventDefault) ---
  _touchStarts = new Map();

  _onTouchStart = (e) => {
    // Stop the browser from scrolling/zooming the canvas.
    e.preventDefault();
    for (const t of e.changedTouches) {
      this._touchStarts.set(t.identifier, {
        x: t.clientX,
        y: t.clientY,
        t: performance.now(),
        moved: false,
      });
    }
    // Resolve taps on touchend via a one-shot listener bound per gesture.
    if (!this._touchEndBound) {
      window.addEventListener('touchend', this._onTouchEnd, { passive: false });
      this._touchEndBound = true;
    }
  };

  _onTouchMove = (e) => {
    e.preventDefault();
    for (const t of e.changedTouches) {
      const s = this._touchStarts.get(t.identifier);
      if (!s) continue;
      if (Math.hypot(t.clientX - s.x, t.clientY - s.y) > MOVE_THRESHOLD) {
        s.moved = true;
      }
    }
  };

  _onTouchEnd = (e) => {
    for (const t of e.changedTouches) {
      const s = this._touchStarts.get(t.identifier);
      this._touchStarts.delete(t.identifier);
      if (!s) continue;
      const dt = performance.now() - s.t;
      if (!s.moved && dt <= TIME_THRESHOLD) {
        this._tryTap(t.clientX, t.clientY);
      }
    }
  };

  /** Convert screen coords to NDC, raycast the hero, fire onTap on a hit. */
  _tryTap(clientX, clientY) {
    const rect = this.canvas.getBoundingClientRect();
    this.ndc.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    this.ndc.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    this.raycaster.setFromCamera(this.ndc, this.camera);
    const hits = this.raycaster.intersectObject(this.hero, true);
    if (hits.length > 0) {
      this.onTap({ point: hits[0].point.clone(), clientX, clientY });
    }
  }
}
