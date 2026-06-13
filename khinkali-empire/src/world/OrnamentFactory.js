/**
 * OrnamentFactory
 * ---------------
 * Procedurally builds Georgian ornaments in gold material:
 *  - a Borjgali (seven-armed rotating sun symbol) as extruded shapes,
 *  - repeating geometric trim strips.
 * No external assets; everything is generated from Three.js geometry.
 */

import * as THREE from 'three';

export class OrnamentFactory {
  static _goldMaterial() {
    return new THREE.MeshStandardMaterial({
      color: 0xffd24a,
      metalness: 0.85,
      roughness: 0.28,
      emissive: 0x4a2e00,
      emissiveIntensity: 0.35,
    });
  }

  /**
   * Build a Borjgali: a central disc with seven curved/triangular sun-arms
   * radiating outward, extruded slightly so it reads as a relief carving.
   * Returns a THREE.Group; the group's userData carries `spin` for animation.
   */
  static createBorjgali(radius = 0.6) {
    const group = new THREE.Group();
    const mat = OrnamentFactory._goldMaterial();

    const extrude = {
      depth: 0.06,
      bevelEnabled: true,
      bevelThickness: 0.015,
      bevelSize: 0.015,
      bevelSegments: 2,
    };

    // --- Central rosette ring ---
    const ringShape = new THREE.Shape();
    ringShape.absarc(0, 0, radius * 0.34, 0, Math.PI * 2, false);
    const hole = new THREE.Path();
    hole.absarc(0, 0, radius * 0.2, 0, Math.PI * 2, true);
    ringShape.holes.push(hole);
    const ring = new THREE.Mesh(
      new THREE.ExtrudeGeometry(ringShape, extrude),
      mat
    );
    group.add(ring);

    // Inner dot.
    const dot = new THREE.Mesh(
      new THREE.CylinderGeometry(radius * 0.1, radius * 0.1, 0.07, 16),
      mat
    );
    dot.rotation.x = Math.PI / 2;
    group.add(dot);

    // --- Seven sun-arms (the defining feature of the Borjgali) ---
    const arms = 7;
    for (let i = 0; i < arms; i++) {
      const angle = (i / arms) * Math.PI * 2;
      const arm = OrnamentFactory._buildArm(radius, mat, extrude);
      arm.rotation.z = angle;
      group.add(arm);
    }

    group.userData.spin = 0.4; // radians/sec for slow rotation
    return group;
  }

  /** One curved swirling sun-arm shape, extruded. */
  static _buildArm(radius, mat, extrude) {
    const shape = new THREE.Shape();
    const inner = radius * 0.34;
    const outer = radius * 0.98;
    const w = radius * 0.16;

    // A tapering arm that curves (comma/swirl shape).
    shape.moveTo(inner, -w * 0.5);
    shape.quadraticCurveTo(
      (inner + outer) * 0.5,
      -w * 1.4,
      outer,
      -w * 0.1
    );
    shape.quadraticCurveTo(outer + w * 0.6, 0, outer, w * 0.1);
    shape.quadraticCurveTo(
      (inner + outer) * 0.5,
      w * 0.9,
      inner,
      w * 0.5
    );
    shape.lineTo(inner, -w * 0.5);

    const mesh = new THREE.Mesh(new THREE.ExtrudeGeometry(shape, extrude), mat);
    return mesh;
  }

  /**
   * A horizontal trim strip of repeating geometric diamonds, used to band the
   * tower or village walls. `count` diamonds across `width` units.
   */
  static createTrim(width = 2, count = 8) {
    const group = new THREE.Group();
    const mat = OrnamentFactory._goldMaterial();
    const step = width / count;
    const size = step * 0.42;

    for (let i = 0; i < count; i++) {
      const x = -width / 2 + step * (i + 0.5);
      const diamond = new THREE.Mesh(
        new THREE.OctahedronGeometry(size, 0),
        mat
      );
      diamond.position.set(x, 0, 0);
      diamond.scale.set(1, 1, 0.35);
      diamond.rotation.z = Math.PI / 4;
      group.add(diamond);
    }
    return group;
  }
}
