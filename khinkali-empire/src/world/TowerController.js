/**
 * TowerController
 * ---------------
 * Procedural Khevsureti stone tower (ხევსურული კოშკი) built from primitives,
 * with a mounted rotating Borjgali and FIVE evolution stages keyed off
 * `totalGoldEarned`. New structures scale-in via tween when a stage unlocks.
 */

import * as THREE from 'three';
import { OrnamentFactory } from './OrnamentFactory.js';
import { Easing } from '../fx/Tween.js';

// Stage thresholds on cumulative gold earned.
export const STAGE_THRESHOLDS = [0, 1e3, 5e4, 2e6, 1e8];

export class TowerController {
  constructor(tweenManager) {
    this.tween = tweenManager;
    this.group = new THREE.Group();
    this.stage = 0; // current evolution stage index (0..4)
    this.stageGroups = []; // meshes added per stage, for scale-in animation
    this._time = 0;
    this._buildBaseMaterials();
  }

  _buildBaseMaterials() {
    // Slightly varied stone material; per-block tint is applied via vertex-less
    // approach by cloning materials with jittered color.
    this.stoneBase = new THREE.MeshStandardMaterial({
      color: 0x8a8275,
      roughness: 0.92,
      metalness: 0.02,
    });
    this.stoneDark = new THREE.MeshStandardMaterial({
      color: 0x6f685c,
      roughness: 0.95,
      metalness: 0.02,
    });
    this.roofMat = new THREE.MeshStandardMaterial({
      color: 0x5a5048,
      roughness: 0.9,
      metalness: 0.03,
    });
    this.woodMat = new THREE.MeshStandardMaterial({
      color: 0x6b4326,
      roughness: 0.85,
    });
  }

  /** Clone the stone material with a small random tint so blocks read as masonry. */
  _stone() {
    const m = this.stoneBase.clone();
    const j = (Math.random() - 0.5) * 0.12;
    m.color.offsetHSL(0, 0, j);
    return m;
  }

  /** Build the full object. Pass initial totalGoldEarned to set the right stage. */
  build(totalGoldEarned = 0) {
    this._buildCoreTower();
    this._mountBorjgali();

    // Pre-create stage container groups (hidden until unlocked).
    for (let s = 0; s < STAGE_THRESHOLDS.length; s++) {
      const g = new THREE.Group();
      g.visible = false;
      this.stageGroups[s] = g;
      this.group.add(g);
    }
    this._populateStage1(this.stageGroups[0]);
    this._populateStage2(this.stageGroups[1]);
    this._populateStage3(this.stageGroups[2]);
    this._populateStage4(this.stageGroups[3]);
    this._populateStage5(this.stageGroups[4]);

    // Reveal stages up to the loaded progress instantly (no animation on load).
    const target = TowerController.stageForGold(totalGoldEarned);
    for (let s = 0; s <= target; s++) {
      this.stageGroups[s].visible = true;
      this.stageGroups[s].scale.set(1, 1, 1);
    }
    this.stage = target;
    return this.group;
  }

  /** Compute the stage index (0-based) for a given cumulative gold value. */
  static stageForGold(totalGold) {
    let s = 0;
    for (let i = 0; i < STAGE_THRESHOLDS.length; i++) {
      if (totalGold >= STAGE_THRESHOLDS[i]) s = i;
    }
    return s;
  }

  /** Normalized smoke/glow intensity 0..1 based on current stage. */
  intensity() {
    return (this.stage + 1) / STAGE_THRESHOLDS.length;
  }

  // ------------------------------------------------------------------
  // Core tower: tapering tiers of stone boxes + a pitched stone roof.
  // ------------------------------------------------------------------
  _buildCoreTower() {
    this.coreTower = new THREE.Group();
    this.coreTower.position.set(0, 0, 0);
    this.group.add(this.coreTower);

    const tiers = 5;
    let y = 0;
    let width = 2.0;
    const tierHeight = 0.9;

    for (let i = 0; i < tiers; i++) {
      const w = width;
      const tier = new THREE.Group();
      // Build each tier from a few stacked "stone" blocks for masonry feel.
      const rows = 2;
      for (let r = 0; r < rows; r++) {
        const blockH = tierHeight / rows;
        const block = new THREE.Mesh(
          new THREE.BoxGeometry(w, blockH * 0.96, w),
          this._stone()
        );
        block.castShadow = true;
        block.receiveShadow = true;
        block.position.y = y + blockH * (r + 0.5);
        // tiny per-block rotation/offset to avoid a perfectly clean stack
        block.position.x = (Math.random() - 0.5) * 0.03;
        block.position.z = (Math.random() - 0.5) * 0.03;
        tier.add(block);
      }
      this.coreTower.add(tier);
      y += tierHeight;
      width *= 0.88; // taper inward each tier
    }

    this.towerTopY = y;
    this.towerTopW = width / 0.88;

    // Pitched stone roof (a low pyramid).
    const roof = new THREE.Mesh(
      new THREE.ConeGeometry(this.towerTopW * 0.85, 1.0, 4),
      this.roofMat
    );
    roof.castShadow = true;
    roof.position.y = y + 0.5;
    roof.rotation.y = Math.PI / 4;
    this.coreTower.add(roof);
    this.roofApexY = y + 1.0;

    // Couple of small window slits (dark recesses).
    const slitMat = new THREE.MeshStandardMaterial({ color: 0x1a1410 });
    for (let i = 0; i < 3; i++) {
      const slit = new THREE.Mesh(
        new THREE.BoxGeometry(0.16, 0.34, 0.06),
        slitMat
      );
      slit.position.set(0, 1.1 + i * 0.9, 1.02 - i * 0.12);
      this.coreTower.add(slit);
    }
  }

  /** Mount the rotating Borjgali on the tower's front face. */
  _mountBorjgali() {
    this.borjgali = OrnamentFactory.createBorjgali(0.55);
    this.borjgali.position.set(0, 1.9, 1.05);
    this.coreTower.add(this.borjgali);

    // A decorative gold trim band around the lower tier.
    const trim = OrnamentFactory.createTrim(2.0, 9);
    trim.position.set(0, 0.45, 1.02);
    this.coreTower.add(trim);
  }

  // ------------------------------------------------------------------
  // Stage population. Each stage adds new meshes to its own group.
  // ------------------------------------------------------------------
  _populateStage1(g) {
    // Stage 1 is the base tower itself; add a faint rough-stone rubble ring.
    for (let i = 0; i < 8; i++) {
      const a = (i / 8) * Math.PI * 2;
      const rock = new THREE.Mesh(
        new THREE.DodecahedronGeometry(0.18 + Math.random() * 0.1, 0),
        this.stoneDark.clone()
      );
      rock.position.set(Math.cos(a) * 1.6, 0.12, Math.sin(a) * 1.6 + 0.4);
      rock.castShadow = true;
      g.add(rock);
    }
  }

  _populateStage2(g) {
    // Taller-looking second body block + a small outbuilding.
    const ext = new THREE.Mesh(
      new THREE.BoxGeometry(1.3, 1.6, 1.3),
      this._stone()
    );
    ext.position.set(0, this.towerTopY + 0.0, 0);
    ext.castShadow = true;
    g.add(ext);
    const extRoof = new THREE.Mesh(
      new THREE.ConeGeometry(0.95, 0.7, 4),
      this.roofMat
    );
    extRoof.position.set(0, this.towerTopY + 1.15, 0);
    extRoof.rotation.y = Math.PI / 4;
    g.add(extRoof);

    // Small outbuilding hut to the side.
    g.add(this._makeHut(-2.3, 0.4));
  }

  _populateStage3(g) {
    // A second tower + wooden balcony, brighter Borjgali is handled in update.
    const t2 = this._makeMiniTower(2.4, 0.2, 3.4);
    g.add(t2);

    // Wooden balcony ring around the main tower.
    const balcony = new THREE.Mesh(
      new THREE.TorusGeometry(1.15, 0.08, 8, 24),
      this.woodMat
    );
    balcony.rotation.x = Math.PI / 2;
    balcony.position.y = 3.1;
    g.add(balcony);
    // Balcony posts.
    for (let i = 0; i < 8; i++) {
      const a = (i / 8) * Math.PI * 2;
      const post = new THREE.Mesh(
        new THREE.CylinderGeometry(0.04, 0.04, 0.4, 6),
        this.woodMat
      );
      post.position.set(Math.cos(a) * 1.15, 3.3, Math.sin(a) * 1.15);
      g.add(post);
    }
    g.add(this._makeHut(2.7, -1.8));
  }

  _populateStage4(g) {
    // Village walls + lanterns + animated banners.
    const wall = new THREE.Group();
    const seg = 16;
    const radius = 4.2;
    for (let i = 0; i < seg; i++) {
      const a = (i / seg) * Math.PI * 2;
      const block = new THREE.Mesh(
        new THREE.BoxGeometry(0.85, 0.9 + Math.random() * 0.25, 0.5),
        this._stone()
      );
      block.position.set(Math.cos(a) * radius, 0.45, Math.sin(a) * radius);
      block.rotation.y = -a;
      block.castShadow = true;
      wall.add(block);
    }
    g.add(wall);

    // Lanterns (emissive point sprites + glowing spheres) on posts.
    this.lanterns = [];
    for (let i = 0; i < 6; i++) {
      const a = (i / 6) * Math.PI * 2;
      const lantern = new THREE.Mesh(
        new THREE.SphereGeometry(0.12, 10, 10),
        new THREE.MeshStandardMaterial({
          color: 0xffb84d,
          emissive: 0xff8a1e,
          emissiveIntensity: 1.4,
        })
      );
      lantern.position.set(Math.cos(a) * (radius - 0.3), 1.1, Math.sin(a) * (radius - 0.3));
      this.lanterns.push(lantern);
      g.add(lantern);
    }

    // Animated banners (cloth strips that wave in update()).
    this.banners = [];
    const bannerMat = new THREE.MeshStandardMaterial({
      color: 0x9c1f2b,
      side: THREE.DoubleSide,
      roughness: 0.8,
    });
    for (let i = 0; i < 3; i++) {
      const banner = new THREE.Mesh(
        new THREE.PlaneGeometry(0.4, 1.0, 1, 6),
        bannerMat
      );
      const a = (i / 3) * Math.PI * 2;
      banner.position.set(Math.cos(a) * 1.2, 4.0, Math.sin(a) * 1.2);
      banner.userData.phase = i * 1.7;
      banner.userData.baseGeo = banner.geometry.attributes.position.array.slice();
      this.banners.push(banner);
      g.add(banner);
    }
  }

  _populateStage5(g) {
    // Fortified empire: outer ring of mini-towers + glowing gold trim.
    for (let i = 0; i < 6; i++) {
      const a = (i / 6) * Math.PI * 2 + 0.3;
      g.add(this._makeMiniTower(Math.cos(a) * 5.2, Math.sin(a) * 5.2, 2.6));
    }
    // Glowing gold trim ring on the ground.
    const goldRing = new THREE.Mesh(
      new THREE.TorusGeometry(5.5, 0.07, 8, 64),
      new THREE.MeshStandardMaterial({
        color: 0xffd24a,
        emissive: 0xffaa20,
        emissiveIntensity: 1.0,
        metalness: 0.9,
        roughness: 0.25,
      })
    );
    goldRing.rotation.x = Math.PI / 2;
    goldRing.position.y = 0.05;
    g.add(goldRing);

    // Crown the main roof with a gold cap.
    const crown = new THREE.Mesh(
      new THREE.IcosahedronGeometry(0.3, 0),
      new THREE.MeshStandardMaterial({
        color: 0xffd24a,
        emissive: 0xffaa20,
        emissiveIntensity: 0.8,
        metalness: 0.95,
        roughness: 0.2,
      })
    );
    crown.position.y = this.roofApexY + 0.3;
    g.add(crown);
  }

  _makeHut(x, z) {
    const hut = new THREE.Group();
    const body = new THREE.Mesh(
      new THREE.BoxGeometry(1.0, 0.8, 1.0),
      this._stone()
    );
    body.position.y = 0.4;
    body.castShadow = true;
    hut.add(body);
    const roof = new THREE.Mesh(
      new THREE.ConeGeometry(0.85, 0.6, 4),
      this.roofMat
    );
    roof.position.y = 1.05;
    roof.rotation.y = Math.PI / 4;
    hut.add(roof);
    hut.position.set(x, 0, z);
    return hut;
  }

  _makeMiniTower(x, z, height) {
    const t = new THREE.Group();
    let y = 0;
    let w = 0.9;
    const tiers = 3;
    for (let i = 0; i < tiers; i++) {
      const h = height / tiers;
      const block = new THREE.Mesh(new THREE.BoxGeometry(w, h, w), this._stone());
      block.position.y = y + h / 2;
      block.castShadow = true;
      t.add(block);
      y += h;
      w *= 0.85;
    }
    const roof = new THREE.Mesh(
      new THREE.ConeGeometry(w * 0.9, 0.6, 4),
      this.roofMat
    );
    roof.position.y = y + 0.3;
    roof.rotation.y = Math.PI / 4;
    t.add(roof);
    t.position.set(x, 0, z);
    return t;
  }

  // ------------------------------------------------------------------
  // Runtime
  // ------------------------------------------------------------------

  /** Recompute the stage from gold; animate newly-unlocked stages scaling in. */
  setProgress(totalGoldEarned) {
    const target = TowerController.stageForGold(totalGoldEarned);
    if (target <= this.stage) return false;
    // Reveal each newly crossed stage with a scale-in pop.
    for (let s = this.stage + 1; s <= target; s++) {
      this._revealStage(s);
    }
    this.stage = target;
    return true;
  }

  _revealStage(s) {
    const g = this.stageGroups[s];
    if (!g) return;
    g.visible = true;
    g.scale.set(0.01, 0.01, 0.01);
    this.tween.add({
      duration: 0.7,
      easing: Easing.easeOutBack,
      onUpdate: (e) => g.scale.set(e, e, e),
      onComplete: () => g.scale.set(1, 1, 1),
    });
  }

  /** World-space position just above the roof apex (smoke origin). */
  getRoofPosition(out) {
    out = out || new THREE.Vector3();
    return out.set(0, this.roofApexY + 0.2, 0);
  }

  /** Trigger a small recoil bounce on the whole tower (on tap). */
  recoil() {
    if (this._recoiling) return;
    this._recoiling = true;
    this.tween.add({
      duration: 0.45,
      easing: Easing.easeOutElastic,
      onUpdate: (e) => {
        const s = 1 - 0.05 * (1 - e);
        this.coreTower.scale.set(s, 1 + (1 - s) * 0.6, s);
      },
      onComplete: () => {
        this.coreTower.scale.set(1, 1, 1);
        this._recoiling = false;
      },
    });
  }

  update(dt) {
    this._time += dt;
    // Borjgali slowly rotates.
    if (this.borjgali) {
      this.borjgali.rotation.z += this.borjgali.userData.spin * dt;
      // Brighten the Borjgali emissive with stage (stage 3+ is "brighter").
      const glow = 0.35 + this.stage * 0.18;
      this.borjgali.traverse((m) => {
        if (m.material && m.material.emissiveIntensity != null) {
          m.material.emissiveIntensity = glow;
        }
      });
    }
    // Lanterns flicker.
    if (this.lanterns) {
      for (const l of this.lanterns) {
        l.material.emissiveIntensity =
          1.2 + Math.sin(this._time * 6 + l.position.x) * 0.4;
      }
    }
    // Banners wave.
    if (this.banners) {
      for (const b of this.banners) {
        const pos = b.geometry.attributes.position;
        const base = b.userData.baseGeo;
        for (let i = 0; i < pos.count; i++) {
          const oy = base[i * 3 + 1];
          const sway = Math.sin(this._time * 3 + oy * 4 + b.userData.phase) * 0.08;
          pos.array[i * 3 + 2] = sway * (0.5 - oy); // more sway at the free end
        }
        pos.needsUpdate = true;
      }
    }
  }
}
