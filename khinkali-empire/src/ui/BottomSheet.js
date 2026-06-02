/**
 * BottomSheet
 * -----------
 * Controls the slide-up/down upgrade panel. Toggle by tapping the handle or by
 * dragging it. Dragging uses translateY for jank-free motion; releasing snaps
 * open or closed based on drag distance/velocity. Respects safe-area bottom
 * inset (handled in CSS).
 */

export class BottomSheet {
  /**
   * @param {HTMLElement} sheetEl
   * @param {HTMLElement} handleEl
   */
  constructor(sheetEl, handleEl) {
    this.sheet = sheetEl;
    this.handle = handleEl;
    this.expanded = false;

    this._dragging = false;
    this._startY = 0;
    this._startTranslate = 0;
    this._collapsedTranslate = 0; // computed from sheet height
    this._lastY = 0;
    this._lastT = 0;
    this._velocity = 0;
    this._movedDuringDrag = false;
  }

  attach() {
    this._computeCollapsed();
    window.addEventListener('resize', () => this._computeCollapsed());

    // Pointer-based drag on the handle (works for mouse + touch).
    this.handle.addEventListener('pointerdown', this._onDown, { passive: true });
    window.addEventListener('pointermove', this._onMove, { passive: false });
    window.addEventListener('pointerup', this._onUp, { passive: true });
  }

  _computeCollapsed() {
    // Collapsed: only the handle (~88px) peeks above the bottom edge.
    const h = this.sheet.offsetHeight;
    this._collapsedTranslate = Math.max(0, h - 88);
    if (!this.expanded && !this._dragging) {
      this._applyTranslate(this._collapsedTranslate);
    }
  }

  _applyTranslate(px) {
    this.sheet.style.transform = `translateY(${px}px)`;
  }

  _onDown = (e) => {
    this._dragging = true;
    this._movedDuringDrag = false;
    this._startY = e.clientY;
    this._lastY = e.clientY;
    this._lastT = performance.now();
    this._velocity = 0;
    this._startTranslate = this.expanded ? 0 : this._collapsedTranslate;
    this.sheet.classList.add('dragging');
    this.handle.setPointerCapture?.(e.pointerId);
  };

  _onMove = (e) => {
    if (!this._dragging) return;
    e.preventDefault();
    const dy = e.clientY - this._startY;
    if (Math.abs(dy) > 4) this._movedDuringDrag = true;
    let next = this._startTranslate + dy;
    next = Math.max(0, Math.min(this._collapsedTranslate, next));
    this._applyTranslate(next);

    const now = performance.now();
    const dt = now - this._lastT;
    if (dt > 0) this._velocity = (e.clientY - this._lastY) / dt; // px/ms
    this._lastY = e.clientY;
    this._lastT = now;
  };

  _onUp = (e) => {
    if (!this._dragging) return;
    this._dragging = false;
    this.sheet.classList.remove('dragging');

    // Tap (no real movement) → toggle.
    if (!this._movedDuringDrag) {
      this.toggle();
      return;
    }

    // Decide snap target from velocity + position.
    const current = this._startTranslate + (this._lastY - this._startY);
    const midpoint = this._collapsedTranslate / 2;
    if (this._velocity < -0.4) {
      this.open();
    } else if (this._velocity > 0.4) {
      this.close();
    } else if (current < midpoint) {
      this.open();
    } else {
      this.close();
    }
  };

  open() {
    this.expanded = true;
    this.sheet.classList.add('expanded');
    this.sheet.style.transform = '';
  }

  close() {
    this.expanded = false;
    this.sheet.classList.remove('expanded');
    this.sheet.style.transform = '';
    this._applyTranslate(this._collapsedTranslate);
  }

  toggle() {
    if (this.expanded) this.close();
    else this.open();
  }
}
