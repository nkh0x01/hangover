/**
 * SceneManager
 * ------------
 * Owns the Three.js renderer, scene, camera and lighting. Frames the tower,
 * applies a warm Georgian-dusk look with fog and a ground plaza, performs a
 * gentle idle camera drift, and handles resize + pixel-ratio capping.
 */

import * as THREE from 'three';

export class SceneManager {
  constructor(canvas) {
    this.canvas = canvas;
    this.scene = new THREE.Scene();
    this.clock = new THREE.Clock();
    this._driftTime = 0;
  }

  init() {
    const { canvas } = this;

    // ---- Renderer ----
    this.renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.setSize(window.innerWidth, window.innerHeight, false);
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.05;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;

    // ---- Scene background: warm dusk gradient via a large sky sphere ----
    this.scene.background = this._makeSkyTexture();
    this.scene.fog = new THREE.Fog(0x3a1518, 14, 38);

    // ---- Camera ----
    this.camera = new THREE.PerspectiveCamera(
      48,
      window.innerWidth / window.innerHeight,
      0.1,
      120
    );
    this.cameraBase = new THREE.Vector3(0, 3.2, 11);
    this.camera.position.copy(this.cameraBase);
    this.lookTarget = new THREE.Vector3(0, 2.4, 0);
    this.camera.lookAt(this.lookTarget);

    this._setupLights();
    this._setupGround();

    window.addEventListener('resize', () => this.resize());
    this.resize();
    return this;
  }

  /** Build a vertical gradient texture (deep wine → amber) for the sky. */
  _makeSkyTexture() {
    const c = document.createElement('canvas');
    c.width = 16;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 256);
    g.addColorStop(0.0, '#1a070a'); // deep wine (top)
    g.addColorStop(0.55, '#3a1014');
    g.addColorStop(0.8, '#7a2b1c');
    g.addColorStop(1.0, '#f2a13b'); // amber (horizon)
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 16, 256);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.magFilter = THREE.LinearFilter;
    tex.minFilter = THREE.LinearFilter;
    return tex;
  }

  _setupLights() {
    // Warm key light (sun low on the horizon), cool fill, soft ambient.
    this.keyLight = new THREE.DirectionalLight(0xffd9a0, 2.0);
    this.keyLight.position.set(6, 9, 6);
    this.keyLight.castShadow = true;
    this.keyLight.shadow.mapSize.set(1024, 1024);
    this.keyLight.shadow.camera.near = 1;
    this.keyLight.shadow.camera.far = 40;
    this.keyLight.shadow.camera.left = -12;
    this.keyLight.shadow.camera.right = 12;
    this.keyLight.shadow.camera.top = 14;
    this.keyLight.shadow.camera.bottom = -6;
    this.keyLight.shadow.bias = -0.0005;
    this.scene.add(this.keyLight);

    this.fillLight = new THREE.DirectionalLight(0x6a88c0, 0.7);
    this.fillLight.position.set(-7, 4, -3);
    this.scene.add(this.fillLight);

    this.ambient = new THREE.HemisphereLight(0xffd9a0, 0x2a0e12, 0.6);
    this.scene.add(this.ambient);

    // A faint warm point light at the tower base for that hearth glow.
    this.hearth = new THREE.PointLight(0xff9a3c, 0.0, 12, 2);
    this.hearth.position.set(0, 1.2, 1.5);
    this.scene.add(this.hearth);
  }

  _setupGround() {
    // Plaza plane: a muted stone disc the village sits on.
    const geo = new THREE.CircleGeometry(20, 48);
    const mat = new THREE.MeshStandardMaterial({
      color: 0x4a2a22,
      roughness: 0.95,
      metalness: 0.0,
    });
    this.ground = new THREE.Mesh(geo, mat);
    this.ground.rotation.x = -Math.PI / 2;
    this.ground.position.y = 0;
    this.ground.receiveShadow = true;
    this.scene.add(this.ground);

    // A subtle ring accent to frame the play area.
    const ringGeo = new THREE.RingGeometry(6.2, 6.6, 64);
    const ringMat = new THREE.MeshBasicMaterial({
      color: 0x7a3b1c,
      transparent: true,
      opacity: 0.35,
      side: THREE.DoubleSide,
    });
    const ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.02;
    this.scene.add(ring);
  }

  add(object) {
    this.scene.add(object);
  }

  /** Idle camera drift + hearth flicker. Called every frame. */
  update(dt) {
    this._driftTime += dt;
    const t = this._driftTime;
    // Gentle orbital sway around the base position.
    this.camera.position.x = this.cameraBase.x + Math.sin(t * 0.18) * 1.4;
    this.camera.position.y = this.cameraBase.y + Math.sin(t * 0.13) * 0.35;
    this.camera.position.z = this.cameraBase.z + Math.cos(t * 0.18) * 0.6;
    this.camera.lookAt(this.lookTarget);

    // Hearth glow flicker scales with whatever intensity was set.
    if (this.hearth.userData.target != null) {
      const flick = 0.85 + Math.sin(t * 9) * 0.06 + Math.sin(t * 13) * 0.04;
      this.hearth.intensity = this.hearth.userData.target * flick;
    }
  }

  /** Set the hearth glow strength (0..~2), e.g. from evolution stage. */
  setHearth(intensity) {
    this.hearth.userData.target = intensity;
  }

  render() {
    this.renderer.render(this.scene, this.camera);
  }

  resize() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.setSize(w, h, false);
  }
}
