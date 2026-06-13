/**
 * main.js — bootstrap & game loop
 * -------------------------------
 * Boot order: load i18n → init GameManager (save + offline) → init UI (shop,
 * HUD) → THEN attempt the 3D world. The 3D layer is a progressive enhancement:
 * if WebGL is unavailable (some in-app browsers, iOS Lockdown Mode, old
 * devices), the game stays fully playable with a 2D fallback khinkali, the shop,
 * idle income and all economy intact. A single requestAnimationFrame loop drives
 * update(dt) + render().
 */

import * as THREE from 'three';
import './styles/main.css';

import { i18n } from './i18n/LocalizationManager.js';
import { SceneManager } from './world/SceneManager.js';
import { TowerController } from './world/TowerController.js';
import { SmokeSystem } from './world/SmokeSystem.js';
import { ParticleSystem } from './fx/ParticleSystem.js';
import { InputManager } from './fx/InputManager.js';
import { TweenManager, squishStretch } from './fx/Tween.js';
import { GameManager } from './core/GameManager.js';
import { UIManager } from './ui/UIManager.js';
import { SaveSystem } from './core/SaveSystem.js';
import { format } from './core/Economy.js';

/**
 * Build the stylized hero khinkali: a squashed sphere "body" pinched into a
 * pleated "kalata" knot on top. Returns a Group used as the 3D tap target.
 */
function createHero() {
  const group = new THREE.Group();

  const bodyMat = new THREE.MeshStandardMaterial({
    color: 0xf6ead0,
    roughness: 0.5,
    metalness: 0.0,
    emissive: 0x3a2c14,
    emissiveIntensity: 0.12,
  });

  const body = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 24), bodyMat);
  body.scale.set(1, 0.82, 1);
  body.castShadow = true;
  body.receiveShadow = true;
  group.add(body);

  const pleatMat = bodyMat.clone();
  pleatMat.color = new THREE.Color(0xeaddbf);
  const pleats = 12;
  for (let i = 0; i < pleats; i++) {
    const a = (i / pleats) * Math.PI * 2;
    const pleat = new THREE.Mesh(
      new THREE.CapsuleGeometry(0.05, 0.5, 4, 6),
      pleatMat
    );
    pleat.position.set(Math.cos(a) * 0.5, 0.55, Math.sin(a) * 0.5);
    pleat.rotation.z = 0.35;
    pleat.rotation.y = -a;
    group.add(pleat);
  }

  const knotMat = bodyMat.clone();
  knotMat.color = new THREE.Color(0xe9d9b6);
  const knot = new THREE.Mesh(
    new THREE.TorusKnotGeometry(0.16, 0.07, 64, 8, 2, 3),
    knotMat
  );
  knot.position.y = 0.95;
  knot.scale.set(1, 0.8, 1);
  knot.castShadow = true;
  group.add(knot);

  const tip = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.22, 10), knotMat);
  tip.position.y = 1.18;
  group.add(tip);

  group.position.set(0, 2.6, 2.6);
  group.userData.baseY = group.position.y;
  return group;
}

/**
 * Stub world FX used when 3D is unavailable, so GameManager can call the same
 * methods without branching. All are cheap no-ops that keep the economy alive.
 */
function makeStubWorld(particles) {
  const tower = {
    stage: 0,
    stageGroups: [],
    setProgress: () => false,
    intensity: () => 0.2,
    recoil: () => {},
    getRoofPosition: (out) => (out ? out.set(0, 5, 0) : { x: 0, y: 5, z: 0 }),
    update: () => {},
  };
  const smoke = { setIntensity: () => {}, setOrigin: () => {}, update: () => {} };
  const sceneManager = {
    setHearth: () => {},
    update: () => {},
    render: () => {},
  };
  return {
    sceneManager,
    scene: null,
    tower,
    smoke,
    particles,
    hero: null,
    squishHero: () => {},
  };
}

/** Try to build the full 3D world. Returns the world object or null on failure. */
function tryBuild3D(canvas, tween, startTotal, floatLayer) {
  // Probe WebGL support up front so we fail fast and cleanly.
  const test = document.createElement('canvas');
  const supported =
    !!(test.getContext('webgl2') || test.getContext('webgl'));
  if (!supported) return null;

  const sceneManager = new SceneManager(canvas).init(); // may throw on context loss

  const tower = new TowerController(tween);
  const towerGroup = tower.build(startTotal);
  sceneManager.add(towerGroup);

  const smoke = new SmokeSystem();
  smoke.setOrigin(tower.getRoofPosition());
  sceneManager.add(smoke.object);

  const hero = createHero();
  sceneManager.add(hero);

  const particles = new ParticleSystem(
    sceneManager.scene,
    sceneManager.camera,
    floatLayer
  );

  return {
    sceneManager,
    scene: sceneManager.scene,
    tower,
    smoke,
    particles,
    hero,
    squishHero: () => squishStretch(hero, tween, 0.4),
  };
}

async function boot() {
  // 1. Localization first so the UI never renders untranslated.
  await i18n.load();
  i18n.applyBindings();

  const canvas = document.getElementById('game-canvas');
  const floatLayer = document.getElementById('float-layer');
  const heroFallback = document.getElementById('hero-fallback');
  const tween = new TweenManager();

  // Peek the save to know the starting evolution stage (no offline credit here).
  const peeked = SaveSystem.load(() => 0);
  const startTotal = peeked.state.totalGoldEarned || 0;

  // 2. Attempt the 3D world. Any failure → graceful 2D fallback mode.
  let world;
  let has3D = false;
  try {
    world = tryBuild3D(canvas, tween, startTotal, floatLayer);
    has3D = !!world;
  } catch (err) {
    console.warn('3D unavailable, running in 2D fallback mode:', err);
    world = null;
  }
  if (!world) {
    // Floats-only particle system (DOM "+N" still works without WebGL).
    const particles = new ParticleSystem(null, null, floatLayer);
    world = makeStubWorld(particles);
    canvas.style.display = 'none';
    heroFallback.classList.remove('hidden');
  }

  // 3. Game state (consumes the save and applies offline earnings).
  const game = new GameManager(world);
  const offline = game.init();

  // 4. UI — shop, HUD, modals. Always runs, with or without 3D.
  const ui = new UIManager(game, i18n);
  ui.init();

  // 5. Welcome-back modal for meaningful offline earnings.
  if (offline && offline.gold > 1 && offline.seconds > 60) {
    ui.showInfo(
      i18n.t('welcome_back'),
      i18n.t('offline_earned', {
        value: format(offline.gold, i18n.getLanguage()),
      }),
      'collect'
    );
  }

  // Shared tap reaction (works in both 3D and 2D modes).
  const particles = world.particles;
  function handleTap(info) {
    const result = game.onTap(info);
    ui.onFirstTap();
    particles.spawnFloatingText(
      info.clientX,
      info.clientY,
      `+${format(result.gain, i18n.getLanguage())}`,
      result.golden
    );
    if (result.golden) {
      particles.spawnFloatingText(
        info.clientX,
        info.clientY - 36,
        i18n.t('golden_khinkali'),
        true
      );
    }
  }

  // 6. Input. 3D → raycast the hero mesh; 2D → tap the fallback element.
  if (has3D) {
    const input = new InputManager(
      canvas,
      world.sceneManager.camera,
      world.hero,
      handleTap
    );
    input.attach();
  } else {
    const onFallbackTap = (e) => {
      const rect = heroFallback.getBoundingClientRect();
      const clientX = e.clientX ?? rect.left + rect.width / 2;
      const clientY = e.clientY ?? rect.top + rect.height / 2;
      handleTap({ point: null, clientX, clientY });
      heroFallback.classList.add('tapped');
      setTimeout(() => heroFallback.classList.remove('tapped'), 90);
    };
    heroFallback.addEventListener('pointerdown', onFallbackTap, {
      passive: true,
    });
  }

  // 7. Main loop with delta time + visibility pause.
  let last = performance.now();
  let running = true;

  function frame(now) {
    requestAnimationFrame(frame);
    if (!running) {
      last = now;
      return;
    }
    let dt = (now - last) / 1000;
    last = now;
    if (dt > 0.1) dt = 0.1; // clamp tab-switch hitches

    game.update(dt);
    tween.update(dt);
    particles.update(dt);

    if (has3D) {
      world.tower.update(dt);
      world.smoke.setOrigin(world.tower.getRoofPosition());
      world.smoke.update(dt);
      world.sceneManager.update(dt);

      const hero = world.hero;
      hero.userData.t = (hero.userData.t || 0) + dt;
      hero.position.y =
        hero.userData.baseY + Math.sin(hero.userData.t * 1.6) * 0.12;
      hero.rotation.y += dt * 0.5;

      world.sceneManager.render();
    }

    ui.update(dt);
  }
  requestAnimationFrame(frame);

  // Pause loop + save when hidden; resume on return.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      running = false;
      game.save();
    } else {
      running = true;
      last = performance.now();
    }
  });
  window.addEventListener('pagehide', () => game.save());
}

boot().catch((err) => {
  // Only reached if even the 2D game fails to start (e.g. i18n load error).
  console.error('Khinkali Empire failed to start:', err);
  const msg = document.createElement('div');
  msg.style.cssText =
    'position:fixed;inset:0;z-index:9999;display:grid;place-items:center;color:#fff;font-family:sans-serif;padding:20px;text-align:center;background:#2a0e12;';
  msg.textContent = 'Failed to start: ' + err.message;
  document.body.appendChild(msg);
});
