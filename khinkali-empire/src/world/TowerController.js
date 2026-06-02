// TowerController.js
// Procedural Khevsureti stone tower ("sacred factory") with a rotating Borjgali,
// Georgian trim, a chimney/vent for steam, and 5 evolution stages keyed to
// totalGoldEarned. New stage meshes scale-in via tween on threshold crossing.

import * as THREE from 'three';
import { OrnamentFactory } from './OrnamentFactory.js';
import { tweenRunner, Easings } from '../fx/Tween.js';

// Evolution thresholds (index 0 unused; stage N at THRESHOLDS[N]).
export const STAGE_THRESHOLDS = [0, 0, 5e3, 1e5, 5e6, 5e8];

export class TowerController {
  constructor(scene) {
    this.scene = scene;
    this.group = new THREE.Group();
    this.scene.add(this.group);
    this.stage = 0;
    this._builtStages = new Set();
    this._stageGroups = {}; // stage -> Group
    this._chimney = null;
    this._borjgali = null;
    this._borjgaliSpin = 0;
    this._stoneTex = this._makeStoneTexture();
  }

  build() {
    this._buildBaseTower();
    // Stage 1 is the base tower itself.
    this.stage = 1;
    this._builtStages.add(1);
    return this;
  }

  // ---- textures ----------------------------------------------------------
  _makeStoneTexture() {
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 128;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#6b5746';
    ctx.fillRect(0, 0, 128, 128);
    // Draw masonry blocks with mortar gaps and per-block tint variation.
    const rows = 6;
    const colH = 128 / rows;
    for (let r = 0; r < rows; r++) {
      const offset = (r % 2) * 16;
      for (let x = -16; x < 128; x += 32) {
        const shade = 90 + Math.floor(Math.random() * 50);
        ctx.fillStyle = `rgb(${shade + 20},${shade},${shade - 18})`;
        ctx.fillRect(x + offset + 1.5, r * colH + 1.5, 32 - 3, colH - 3);
      }
    }
    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
  }

  _stoneMaterial(repeatX = 2, repeatY = 3, tint = 0xffffff) {
    const tex = this._stoneTex.clone();
    tex.needsUpdate = true;
    tex.repeat.set(repeatX, repeatY);
    return new THREE.MeshStandardMaterial({
      map: tex,
      color: tint,
      roughness: 0.92,
      metalness: 0.02,
    });
  }

  // ---- base tower (stage 1) ---------------------------------------------
  _buildBaseTower() {
    const tower = new THREE.Group();

    // Four tapering stone tiers.
    const tiers = [
      { w: 4.0, h: 3.0, y: 1.5 },
      { w: 3.5, h: 2.8, y: 4.4 },
      { w: 3.0, h: 2.6, y: 7.0 },
      { w: 2.5, h: 2.4, y: 9.5 },
    ];
    tiers.forEach((t, i) => {
      const mat = this._stoneMaterial(2, Math.max(2, Math.round(t.h)), 0xffffff);
      const block = new THREE.Mesh(new THREE.BoxGeometry(t.w, t.h, t.w), mat);
      block.position.y = t.y;
      tower.add(block);
      // A thin gold trim line between tiers.
      if (i < tiers.length - 1) {
        const trim = new THREE.Mesh(
          new THREE.BoxGeometry(t.w + 0.08, 0.12, t.w + 0.08),
          OrnamentFactory.goldMaterial(0.2)
        );
        trim.position.y = t.y + t.h / 2;
        tower.add(trim);
      }
    });

    // Pitched stone pyramidal roof.
    const roof = new THREE.Mesh(
      new THREE.ConeGeometry(2.0, 2.2, 4),
      new THREE.MeshStandardMaterial({ color: 0x4a3326, roughness: 0.9 })
    );
    roof.position.y = 11.8;
    roof.rotation.y = Math.PI / 4;
    tower.add(roof);

    // Chimney / steam vent at the apex.
    const chimney = new THREE.Mesh(
      new THREE.CylinderGeometry(0.32, 0.4, 0.9, 12),
      this._stoneMaterial(1, 1, 0xb0a090)
    );
    chimney.position.y = 13.1;
    tower.add(chimney);
    this._chimney = chimney;

    // Slit windows for character.
    const winMat = new THREE.MeshStandardMaterial({ color: 0x1a0c08, roughness: 1 });
    [3.0, 5.8, 8.2].forEach((wy, i) => {
      const w = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.9, 0.2), winMat);
      w.position.set(0, wy, (tiers[i].w / 2) + 0.01);
      tower.add(w);
    });

    // Mounted Borjgali on the upper tier, facing the camera.
    const borjgali = OrnamentFactory.createBorjgali(1.0, 0.25);
    borjgali.position.set(0, 9.6, 1.32);
    tower.add(borjgali);
    this._borjgali = borjgali;

    // Georgian ornament band around the base.
    const band = OrnamentFactory.createOrnamentBand(2.3, 14, 0.14, 0.2);
    band.position.y = 0.4;
    tower.add(band);

    this.group.add(tower);
    this._baseTower = tower;
  }

  // ---- stage content -----------------------------------------------------
  _buildStage(stage) {
    if (this._builtStages.has(stage)) return null;
    let g = null;
    if (stage === 2) g = this._buildStage2();
    else if (stage === 3) g = this._buildStage3();
    else if (stage === 4) g = this._buildStage4();
    else if (stage === 5) g = this._buildStage5();
    if (g) {
      this._stageGroups[stage] = g;
      this.group.add(g);
      this._builtStages.add(stage);
    }
    return g;
  }

  _buildStage2() {
    // Cleaner stone + a small outbuilding beside the tower.
    const g = new THREE.Group();
    const out = new THREE.Mesh(
      new THREE.BoxGeometry(2.4, 2.2, 2.4),
      this._stoneMaterial(2, 2, 0xe8e0d0)
    );
    out.position.set(-3.6, 1.1, 1.2);
    g.add(out);
    const outRoof = new THREE.Mesh(
      new THREE.ConeGeometry(1.9, 1.4, 4),
      new THREE.MeshStandardMaterial({ color: 0x5a3a28, roughness: 0.9 })
    );
    outRoof.position.set(-3.6, 2.9, 1.2);
    outRoof.rotation.y = Math.PI / 4;
    g.add(outRoof);
    // Brighten the base tower stone slightly.
    return g;
  }

  _buildStage3() {
    // A second tower + wooden balcony, brighter Borjgali.
    const g = new THREE.Group();
    const t2 = new THREE.Group();
    const tiers = [
      { w: 2.8, h: 2.6, y: 1.3 },
      { w: 2.4, h: 2.4, y: 3.8 },
      { w: 2.0, h: 2.2, y: 6.0 },
    ];
    tiers.forEach((t) => {
      const block = new THREE.Mesh(new THREE.BoxGeometry(t.w, t.h, t.w), this._stoneMaterial(2, 2, 0xf0e8d8));
      block.position.y = t.y;
      t2.add(block);
    });
    const roof = new THREE.Mesh(
      new THREE.ConeGeometry(1.6, 1.6, 4),
      new THREE.MeshStandardMaterial({ color: 0x4a3326, roughness: 0.9 })
    );
    roof.position.y = 7.9;
    roof.rotation.y = Math.PI / 4;
    t2.add(roof);
    t2.position.set(3.8, 0, -0.4);
    g.add(t2);

    // Wooden balcony around the main tower.
    const balcony = new THREE.Mesh(
      new THREE.BoxGeometry(3.4, 0.2, 3.4),
      new THREE.MeshStandardMaterial({ color: 0x6b4a2a, roughness: 0.9 })
    );
    balcony.position.y = 6.0;
    g.add(balcony);
    const railing = OrnamentFactory.createTrimRing(1.9, 0.06, 0.25);
    railing.position.y = 6.5;
    g.add(railing);

    // Brighten the Borjgali emissive.
    if (this._borjgali) {
      this._borjgali.traverse((m) => {
        if (m.material && m.material.emissiveIntensity !== undefined) m.material.emissiveIntensity = 0.5;
      });
    }
    return g;
  }

  _buildStage4() {
    // Village walls + lanterns + banners.
    const g = new THREE.Group();
    const wallMat = this._stoneMaterial(6, 1, 0xddd2c0);
    const segs = [
      { x: 0, z: 6.5, rot: 0, len: 13 },
      { x: 0, z: -6.5, rot: 0, len: 13 },
      { x: 6.5, z: 0, rot: Math.PI / 2, len: 13 },
      { x: -6.5, z: 0, rot: Math.PI / 2, len: 13 },
    ];
    segs.forEach((s) => {
      const wall = new THREE.Mesh(new THREE.BoxGeometry(s.len, 1.8, 0.5), wallMat);
      wall.position.set(s.x, 0.9, s.z);
      wall.rotation.y = s.rot;
      g.add(wall);
    });
    // Lanterns on the corners.
    [
      [6.3, 6.3],
      [-6.3, 6.3],
      [6.3, -6.3],
      [-6.3, -6.3],
    ].forEach(([x, z]) => {
      const lantern = OrnamentFactory.createLantern();
      lantern.position.set(x, 2.1, z);
      g.add(lantern);
    });
    // Banners on the main tower.
    [-1, 1].forEach((side) => {
      const banner = OrnamentFactory.createBanner(0.7, 2.0);
      banner.position.set(side * 1.4, 7.4, 1.3);
      g.add(banner);
    });
    return g;
  }

  _buildStage5() {
    // Fortified mountain "empire" with glowing gold trim.
    const g = new THREE.Group();
    // Outer fortified ramparts (taller wall ring).
    const rampMat = this._stoneMaterial(10, 2, 0xcfc4b0);
    for (let i = 0; i < 16; i++) {
      const a = (i / 16) * Math.PI * 2;
      const merlon = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.9, 0.6), rampMat);
      merlon.position.set(Math.cos(a) * 8.5, 2.6, Math.sin(a) * 8.5);
      merlon.lookAt(0, 2.6, 0);
      g.add(merlon);
    }
    const baseWall = new THREE.Mesh(new THREE.CylinderGeometry(8.6, 9.0, 2.4, 32, 1, true), rampMat);
    baseWall.position.y = 1.2;
    g.add(baseWall);

    // Glowing gold crown trim around the main tower.
    const crown = OrnamentFactory.createOrnamentBand(1.6, 18, 0.16, 0.9);
    crown.position.y = 10.2;
    g.add(crown);
    const goldRing = OrnamentFactory.createTrimRing(2.0, 0.08, 0.9);
    goldRing.position.y = 0.6;
    g.add(goldRing);

    // A warm rim light to sell the "glowing" empire.
    const empireLight = new THREE.PointLight(0xffcf6a, 1.2, 24, 2);
    empireLight.position.set(0, 11, 3);
    g.add(empireLight);
    return g;
  }

  // ---- evolution ---------------------------------------------------------
  stageForGold(total) {
    let s = 1;
    for (let i = 1; i < STAGE_THRESHOLDS.length; i++) {
      if (total >= STAGE_THRESHOLDS[i]) s = i;
    }
    return s;
  }

  /** Animate scale-in of newly crossed stages. Called when gold grows. */
  updateStage(totalGold, animate = true) {
    const target = this.stageForGold(totalGold);
    while (this.stage < target) {
      this.stage += 1;
      const g = this._buildStage(this.stage);
      if (g && animate) {
        g.scale.set(0.001, 0.001, 0.001);
        tweenRunner.add({
          duration: 0.8,
          ease: Easings.easeOutBack,
          onUpdate: (e) => g.scale.set(e, e, e),
          onComplete: () => g.scale.set(1, 1, 1),
        });
      } else if (g) {
        g.scale.set(1, 1, 1);
      }
    }
  }

  /** Snap to the correct stage instantly (used on load). */
  setStageInstant(totalGold) {
    const target = this.stageForGold(totalGold);
    while (this.stage < target) {
      this.stage += 1;
      const g = this._buildStage(this.stage);
      if (g) g.scale.set(1, 1, 1);
    }
  }

  /** World-space position of the chimney top (for steam emission). */
  getChimneyWorldPosition(out) {
    const v = out || new THREE.Vector3();
    if (this._chimney) {
      this._chimney.getWorldPosition(v);
      v.y += 0.5;
    } else {
      v.set(0, 13.6, 0);
    }
    return v;
  }

  update(dt) {
    // Slowly rotate the Borjgali sun.
    if (this._borjgali) {
      this._borjgaliSpin += dt * 0.4;
      this._borjgali.rotation.z = this._borjgaliSpin;
    }
  }
}

export default TowerController;
