// InputManager.js
// Mobile-correct input. Uses Pointer Events (each touch = unique pointerId, so
// multi-touch rapid tapping works for free). A "tap" = down+up on the same
// target, moved < 12px, within < 350ms — so dragging the shop sheet never fires
// a tap. Also provides accurate canvas raycasting against the 3D tower.

import * as THREE from 'three';

const TAP_MOVE_MAX = 12; // px
const TAP_TIME_MAX = 350; // ms

export class InputManager {
  constructor() {
    this.canvas = null;
    this.camera = null;
    this.raycaster = new THREE.Raycaster();
    this._ndc = new THREE.Vector2();
    this._towerObjects = [];
    this._towerTapHandler = null;
    this._cleanups = [];
  }

  attach(canvas, camera) {
    this.canvas = canvas;
    this.camera = camera;
    this._attachCanvasRaycast();
    return this;
  }

  /** Provide the tower root group whose meshes should be raycast-tappable. */
  setTowerTarget(object3D, handler) {
    this._towerObjects = [object3D];
    this._towerTapHandler = handler;
  }

  // ---- canvas raycast taps (3D tower) -----------------------------------
  _attachCanvasRaycast() {
    const canvas = this.canvas;
    const tracked = new Map();

    const onDown = (e) => {
      tracked.set(e.pointerId, { x: e.clientX, y: e.clientY, t: performance.now(), cancelled: false });
    };
    const onMove = (e) => {
      const p = tracked.get(e.pointerId);
      if (!p) return;
      if (Math.hypot(e.clientX - p.x, e.clientY - p.y) > TAP_MOVE_MAX) p.cancelled = true;
    };
    const onUp = (e) => {
      const p = tracked.get(e.pointerId);
      tracked.delete(e.pointerId);
      if (!p || p.cancelled) return;
      if (performance.now() - p.t > TAP_TIME_MAX) return;
      this._tryTowerTap(e.clientX, e.clientY);
    };
    const onCancel = (e) => tracked.delete(e.pointerId);

    canvas.addEventListener('pointerdown', onDown);
    canvas.addEventListener('pointermove', onMove);
    canvas.addEventListener('pointerup', onUp);
    canvas.addEventListener('pointercancel', onCancel);
    this._cleanups.push(() => {
      canvas.removeEventListener('pointerdown', onDown);
      canvas.removeEventListener('pointermove', onMove);
      canvas.removeEventListener('pointerup', onUp);
      canvas.removeEventListener('pointercancel', onCancel);
    });
  }

  _tryTowerTap(clientX, clientY) {
    if (!this._towerTapHandler || this._towerObjects.length === 0) return;
    // Normalize against the canvas bounding rect (not the window).
    const rect = this.canvas.getBoundingClientRect();
    this._ndc.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    this._ndc.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    this.raycaster.setFromCamera(this._ndc, this.camera);
    const hits = this.raycaster.intersectObjects(this._towerObjects, true);
    if (hits.length > 0) this._towerTapHandler(clientX, clientY);
  }

  /**
   * Register a robust tap handler on a DOM element.
   * Supports multi-touch (each pointerId tracked independently) so rapid
   * multi-finger tapping on the assemble target all register.
   * options.preventScroll -> preventDefault on pointerdown (stops scroll/zoom).
   */
  onTap(el, handler, options = {}) {
    const tracked = new Map();

    const onDown = (e) => {
      if (options.preventScroll) e.preventDefault();
      tracked.set(e.pointerId, { x: e.clientX, y: e.clientY, t: performance.now(), cancelled: false });
    };
    const onMove = (e) => {
      const p = tracked.get(e.pointerId);
      if (!p) return;
      if (Math.hypot(e.clientX - p.x, e.clientY - p.y) > TAP_MOVE_MAX) p.cancelled = true;
    };
    const onUp = (e) => {
      const p = tracked.get(e.pointerId);
      tracked.delete(e.pointerId);
      if (!p || p.cancelled) return;
      if (performance.now() - p.t > TAP_TIME_MAX) return;
      handler(e.clientX, e.clientY, e);
    };
    const onCancel = (e) => tracked.delete(e.pointerId);

    el.addEventListener('pointerdown', onDown, { passive: !options.preventScroll });
    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerup', onUp);
    el.addEventListener('pointercancel', onCancel);

    const cleanup = () => {
      el.removeEventListener('pointerdown', onDown);
      el.removeEventListener('pointermove', onMove);
      el.removeEventListener('pointerup', onUp);
      el.removeEventListener('pointercancel', onCancel);
    };
    this._cleanups.push(cleanup);
    return cleanup;
  }

  destroy() {
    this._cleanups.forEach((fn) => fn());
    this._cleanups.length = 0;
  }
}

export default InputManager;
