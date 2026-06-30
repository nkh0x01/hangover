// SceneManager.js
// three.js scene, camera, lights, renderer and resize handling.
// The tower is framed toward the upper portion of the canvas; the prep table
// and shop DOM overlay the lower portion.

import * as THREE from 'three';

export class SceneManager {
  constructor(canvas) {
    this.canvas = canvas;
    this.scene = new THREE.Scene();
    this.clock = new THREE.Clock();
    this._driftT = 0;
  }

  init() {
    const canvas = this.canvas;

    this.renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.12;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;

    // Camera — frames the tower toward the upper area of the view.
    this.camera = new THREE.PerspectiveCamera(46, 1, 0.1, 200);
    this.camera.position.set(0, 6.4, 15.5);
    this._lookTarget = new THREE.Vector3(0, 8.2, 0);
    this.camera.lookAt(this._lookTarget);

    // Georgian dusk gradient background (deep wine -> amber).
    this.scene.background = this._makeGradientBackground();
    this.scene.fog = new THREE.Fog(0x35121c, 22, 60);

    this._addLights();
    this._addPlaza();

    // Root group that controllers attach meshes to.
    this.root = new THREE.Group();
    this.scene.add(this.root);

    this.resize();
    window.addEventListener('resize', () => this.resize());
    window.addEventListener('orientationchange', () => this.resize());

    return this;
  }

  _makeGradientBackground() {
    const c = document.createElement('canvas');
    c.width = 8;
    c.height = 256;
    const ctx = c.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 256);
    grad.addColorStop(0.0, '#1a0710'); // deep wine top
    grad.addColorStop(0.45, '#3a1320');
    grad.addColorStop(0.72, '#7a3520');
    grad.addColorStop(1.0, '#e8a84a'); // amber horizon
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 8, 256);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
  }

  _addLights() {
    // Warm key light (low dusk sun).
    const key = new THREE.DirectionalLight(0xffcf8a, 1.5);
    key.position.set(6, 12, 8);
    this.scene.add(key);

    // Cool fill from the opposite side.
    const fill = new THREE.DirectionalLight(0x7fa8ff, 0.45);
    fill.position.set(-8, 6, -4);
    this.scene.add(fill);

    // Hemisphere + ambient for soft base illumination.
    const hemi = new THREE.HemisphereLight(0xffd9a0, 0x2a1018, 0.55);
    this.scene.add(hemi);
    this.scene.add(new THREE.AmbientLight(0x46202c, 0.5));

    // A subtle warm glow near the tower base.
    const glow = new THREE.PointLight(0xffb45a, 0.6, 30, 2);
    glow.position.set(0, 3, 4);
    this.scene.add(glow);
    this._towerGlow = glow;
  }

  _addPlaza() {
    const geo = new THREE.CircleGeometry(26, 48);
    const mat = new THREE.MeshStandardMaterial({
      color: 0x3a2018,
      roughness: 0.95,
      metalness: 0.0,
    });
    const ground = new THREE.Mesh(geo, mat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = 0;
    this.scene.add(ground);

    // Faint ring detailing on the plaza.
    const ring = new THREE.Mesh(
      new THREE.RingGeometry(7.5, 8.2, 48),
      new THREE.MeshStandardMaterial({ color: 0x6a4326, roughness: 0.8, side: THREE.DoubleSide })
    );
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.02;
    this.scene.add(ring);
  }

  /** Pulse the base glow with production intensity (0..1). */
  setGlow(intensity) {
    if (this._towerGlow) this._towerGlow.intensity = 0.5 + intensity * 1.6;
  }

  resize() {
    const rect = this.canvas.getBoundingClientRect();
    const w = Math.max(1, rect.width || window.innerWidth);
    const h = Math.max(1, rect.height || window.innerHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
  }

  update(dt) {
    // Gentle idle camera drift around the look target.
    this._driftT += dt;
    const driftX = Math.sin(this._driftT * 0.18) * 1.1;
    const driftY = Math.sin(this._driftT * 0.13 + 1.0) * 0.5;
    this.camera.position.x = driftX;
    this.camera.position.y = 6.4 + driftY;
    this.camera.lookAt(this._lookTarget);
  }

  render() {
    this.renderer.render(this.scene, this.camera);
  }
}

export default SceneManager;
