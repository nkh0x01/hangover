/**
 * SmokeSystem
 * -----------
 * Looping additive smoke particles drifting up from the tower roof and
 * dissipating. Intensity (spawn rate, opacity, count) scales with KPS /
 * evolution stage. Uses a single THREE.Points cloud with recycled particles.
 */

import * as THREE from 'three';

const MAX_PARTICLES = 80;

export class SmokeSystem {
  constructor() {
    this.origin = new THREE.Vector3(0, 5, 0);
    this.intensity = 0.2; // 0..1, controls spawn rate & opacity
    this._spawnAccumulator = 0;
    this._time = 0;
    this._build();
  }

  _build() {
    const positions = new Float32Array(MAX_PARTICLES * 3);
    const sizes = new Float32Array(MAX_PARTICLES);
    const alphas = new Float32Array(MAX_PARTICLES);

    // Per-particle CPU state (life, velocity).
    this.particles = [];
    for (let i = 0; i < MAX_PARTICLES; i++) {
      this.particles.push({
        active: false,
        life: 0,
        maxLife: 1,
        vx: 0,
        vy: 0,
        vz: 0,
        size: 0,
      });
      alphas[i] = 0;
      sizes[i] = 0;
    }

    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geo.setAttribute('size', new THREE.BufferAttribute(sizes, 1));
    geo.setAttribute('alpha', new THREE.BufferAttribute(alphas, 1));
    this.geometry = geo;

    // Soft round smoke sprite generated on a canvas.
    const tex = this._makeSmokeTexture();

    const mat = new THREE.ShaderMaterial({
      uniforms: { uTex: { value: tex }, uColor: { value: new THREE.Color(0xcfcfcf) } },
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      vertexShader: `
        attribute float size;
        attribute float alpha;
        varying float vAlpha;
        void main() {
          vAlpha = alpha;
          vec4 mv = modelViewMatrix * vec4(position, 1.0);
          gl_PointSize = size * (300.0 / -mv.z);
          gl_Position = projectionMatrix * mv;
        }
      `,
      fragmentShader: `
        uniform sampler2D uTex;
        uniform vec3 uColor;
        varying float vAlpha;
        void main() {
          vec4 t = texture2D(uTex, gl_PointCoord);
          gl_FragColor = vec4(uColor, t.a * vAlpha);
        }
      `,
    });

    this.points = new THREE.Points(geo, mat);
    this.points.frustumCulled = false;
    this.object = this.points;
  }

  _makeSmokeTexture() {
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, 'rgba(255,255,255,0.9)');
    g.addColorStop(0.4, 'rgba(220,220,220,0.4)');
    g.addColorStop(1, 'rgba(200,200,200,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 64, 64);
    const tex = new THREE.CanvasTexture(c);
    return tex;
  }

  setOrigin(vec3) {
    this.origin.copy(vec3);
  }

  /** intensity in 0..1 from TowerController.intensity(). */
  setIntensity(v) {
    this.intensity = Math.max(0, Math.min(1, v));
  }

  _spawn() {
    const p = this.particles.find((x) => !x.active);
    if (!p) return;
    p.active = true;
    p.life = 0;
    p.maxLife = 2.2 + Math.random() * 1.6;
    p.vx = (Math.random() - 0.5) * 0.25;
    p.vy = 0.5 + Math.random() * 0.4 + this.intensity * 0.5;
    p.vz = (Math.random() - 0.5) * 0.25;
    p.size = 18 + Math.random() * 14 + this.intensity * 20;
    p.x = this.origin.x + (Math.random() - 0.5) * 0.3;
    p.y = this.origin.y;
    p.z = this.origin.z + (Math.random() - 0.5) * 0.3;
  }

  update(dt) {
    this._time += dt;

    // Spawn rate scales with intensity (more progress → thicker smoke).
    const rate = 4 + this.intensity * 28; // particles/sec
    this._spawnAccumulator += rate * dt;
    while (this._spawnAccumulator >= 1) {
      this._spawn();
      this._spawnAccumulator -= 1;
    }

    const pos = this.geometry.attributes.position.array;
    const size = this.geometry.attributes.size.array;
    const alpha = this.geometry.attributes.alpha.array;

    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];
      if (!p.active) {
        alpha[i] = 0;
        size[i] = 0;
        continue;
      }
      p.life += dt;
      if (p.life >= p.maxLife) {
        p.active = false;
        alpha[i] = 0;
        size[i] = 0;
        continue;
      }
      const t = p.life / p.maxLife;
      // Drift + a little curl.
      p.x += (p.vx + Math.sin(this._time + i) * 0.08) * dt;
      p.y += p.vy * dt;
      p.z += p.vz * dt;

      pos[i * 3] = p.x;
      pos[i * 3 + 1] = p.y;
      pos[i * 3 + 2] = p.z;
      size[i] = p.size * (0.6 + t * 0.9); // grow as it rises
      // Fade in then out; overall opacity scaled by intensity.
      const fade = Math.sin(t * Math.PI);
      alpha[i] = fade * (0.18 + this.intensity * 0.4);
    }

    this.geometry.attributes.position.needsUpdate = true;
    this.geometry.attributes.size.needsUpdate = true;
    this.geometry.attributes.alpha.needsUpdate = true;
  }
}
