// OrnamentFactory.js
// Procedural Georgian ornaments: the Borjgali (seven-arm sun) and decorative
// gold trim bands, all built from three.js primitives.

import * as THREE from 'three';

export class OrnamentFactory {
  /** Shared-style gold material (new instance so brightness can be tuned). */
  static goldMaterial(emissive = 0.25) {
    return new THREE.MeshStandardMaterial({
      color: 0xf0c662,
      metalness: 0.85,
      roughness: 0.28,
      emissive: 0x6a4410,
      emissiveIntensity: emissive,
    });
  }

  /**
   * Build a Borjgali: a central hub with seven curved, swirling arms.
   * Returns a Group meant to be rotated slowly by the caller.
   */
  static createBorjgali(radius = 1.0, brightness = 0.25) {
    const group = new THREE.Group();
    const mat = OrnamentFactory.goldMaterial(brightness);

    // Central hub disc.
    const hub = new THREE.Mesh(new THREE.CylinderGeometry(radius * 0.26, radius * 0.26, radius * 0.12, 24), mat);
    hub.rotation.x = Math.PI / 2;
    group.add(hub);

    // A small raised boss in the center.
    const boss = new THREE.Mesh(new THREE.SphereGeometry(radius * 0.14, 16, 12), mat);
    boss.position.z = radius * 0.06;
    group.add(boss);

    // Seven curved arms (comma-like spokes) arranged radially.
    const arms = 7;
    for (let i = 0; i < arms; i++) {
      const arm = OrnamentFactory._createArm(radius, mat);
      arm.rotation.z = (i / arms) * Math.PI * 2;
      group.add(arm);
    }

    // Outer trim ring.
    const ring = new THREE.Mesh(new THREE.TorusGeometry(radius * 1.04, radius * 0.05, 10, 48), mat);
    group.add(ring);

    return group;
  }

  /** One swirling arm of the Borjgali, built from a curved tube. */
  static _createArm(radius, mat) {
    const arm = new THREE.Group();
    // Curve: start near the hub and sweep outward with a hook.
    const curve = new THREE.CatmullRomCurve3([
      new THREE.Vector3(radius * 0.22, 0, 0),
      new THREE.Vector3(radius * 0.5, radius * 0.16, 0),
      new THREE.Vector3(radius * 0.78, radius * 0.42, 0),
      new THREE.Vector3(radius * 0.9, radius * 0.74, 0),
      new THREE.Vector3(radius * 0.74, radius * 0.96, 0),
    ]);
    const tube = new THREE.Mesh(
      new THREE.TubeGeometry(curve, 24, radius * 0.06, 8, false),
      mat
    );
    arm.add(tube);
    // Teardrop tip.
    const tip = new THREE.Mesh(new THREE.SphereGeometry(radius * 0.085, 12, 10), mat);
    tip.position.set(radius * 0.74, radius * 0.96, 0);
    arm.add(tip);
    return arm;
  }

  /**
   * A decorative band of small gold diamonds around a circle — Georgian trim.
   * Returns a Group positioned at the origin (caller sets y / parent).
   */
  static createOrnamentBand(radius, count = 12, size = 0.16, brightness = 0.25) {
    const group = new THREE.Group();
    const mat = OrnamentFactory.goldMaterial(brightness);
    const geo = new THREE.OctahedronGeometry(size, 0);
    for (let i = 0; i < count; i++) {
      const a = (i / count) * Math.PI * 2;
      const d = new THREE.Mesh(geo, mat);
      d.position.set(Math.cos(a) * radius, 0, Math.sin(a) * radius);
      d.rotation.y = a;
      group.add(d);
    }
    return group;
  }

  /** A simple gold trim ring (torus) for edges / balconies. */
  static createTrimRing(radius, thickness = 0.05, brightness = 0.25) {
    const mat = OrnamentFactory.goldMaterial(brightness);
    const ring = new THREE.Mesh(new THREE.TorusGeometry(radius, thickness, 10, 48), mat);
    ring.rotation.x = Math.PI / 2;
    return ring;
  }

  /** A hanging banner (cloth-ish quad) in deep red with gold trim. */
  static createBanner(width = 0.6, height = 1.4) {
    const group = new THREE.Group();
    const cloth = new THREE.Mesh(
      new THREE.PlaneGeometry(width, height),
      new THREE.MeshStandardMaterial({ color: 0x9c1f2b, roughness: 0.8, side: THREE.DoubleSide })
    );
    cloth.position.y = -height / 2;
    group.add(cloth);
    const trim = new THREE.Mesh(
      new THREE.BoxGeometry(width * 1.05, 0.06, 0.04),
      OrnamentFactory.goldMaterial(0.3)
    );
    trim.position.y = 0;
    group.add(trim);
    // Small gold cross emblem.
    const emblemMat = OrnamentFactory.goldMaterial(0.35);
    const v = new THREE.Mesh(new THREE.BoxGeometry(0.05, height * 0.4, 0.02), emblemMat);
    const h = new THREE.Mesh(new THREE.BoxGeometry(width * 0.5, 0.05, 0.02), emblemMat);
    v.position.y = -height / 2;
    h.position.y = -height * 0.4;
    group.add(v, h);
    return group;
  }

  /** A small glowing lantern. */
  static createLantern() {
    const group = new THREE.Group();
    const cage = new THREE.Mesh(
      new THREE.CylinderGeometry(0.12, 0.14, 0.28, 8),
      OrnamentFactory.goldMaterial(0.3)
    );
    const flame = new THREE.Mesh(
      new THREE.SphereGeometry(0.07, 10, 8),
      new THREE.MeshStandardMaterial({ color: 0xffcf6a, emissive: 0xffae3a, emissiveIntensity: 1.6 })
    );
    group.add(cage, flame);
    const light = new THREE.PointLight(0xffb24a, 0.5, 6, 2);
    group.add(light);
    return group;
  }
}

export default OrnamentFactory;
