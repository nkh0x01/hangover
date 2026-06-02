/**
 * main.js — bootstrap & game loop
 * -------------------------------
 * Boot order: load i18n → init scene → build world (tower/hero/ornaments/smoke)
 * → init GameManager (save + offline) → init UI → attach input → start the
 * single requestAnimationFrame loop driving update(dt) + render().
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
 * pleated "kalata" knot on top (a small twisted torus-knot + cone). Soft,
 * subsurface-ish material. Returns a Group used as the tap target.
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

  // Body: a squashed sphere with subtle vertical pleats via slight scaling.
  const body = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 24), bodyMat);
  body.scale.set(1, 0.82, 1);
  body.castShadow = true;
  body.receiveShadow = true;
  group.add(body);

  // Pleats: thin ridges around the upper body to suggest folded dough.
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

  // Kalata knot on top: a small twisted torus knot + crowning cone.
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

  group.position.set(0, 2.6, 2.6); // floats in front of the tower
  group.userData.baseY = group.position.y;
  return group;
}

async function boot() {
  // 1. Localization first so the UI never renders untranslated.
  await i18n.load();
  i18n.applyBindings();

  // 2. Scene.
  const canvas = document.getElementById('game-canvas');
  const sceneManager = new SceneManager(canvas).init();

  // 3. World: tween runner, tower (+ornaments), smoke, hero, particles.
  const tween = new TweenManager();

  // Determine starting stage from the (peeked) save without consuming it.
  const peeked = SaveSystem.load(() => 0); // no offline credit here; just read
  const startTotal = peeked.state.totalGoldEarned || 0;

  const tower = new TowerController(tween);
  const towerGroup = tower.build(startTotal);
  sceneManager.add(towerGroup);

  const smoke = new SmokeSystem();
  smoke.setOrigin(tower.getRoofPosition());
  sceneManager.add(smoke.object);

  const hero = createHero();
  sceneManager.add(hero);

  const floatLayer = document.getElementById('float-layer');
  const particles = new ParticleSystem(
    sceneManager.scene,
    sceneManager.camera,
    floatLayer
  );

  // World facade passed to GameManager for triggering FX.
  const world = {
    sceneManager,
    scene: sceneManager.scene,
    tower,
    smoke,
    particles,
    hero,
    squishHero: () => squishStretch(hero, tween, 0.4),
  };

  // 4. Game state (this consumes the save and applies offline earnings).
  const game = new GameManager(world);
  const offline = game.init();

  // 5. UI.
  const ui = new UIManager(game, i18n);
  ui.init();

  // Show "welcome back" if meaningful offline earnings accrued.
  if (offline && offline.gold > 1 && offline.seconds > 60) {
    ui.showInfo(
      i18n.t('welcome_back'),
      i18n.t('offline_earned', {
        value: format(offline.gold, i18n.getLanguage()),
      }),
      'collect'
    );
  }

  // 6. Input: raycast taps against the hero → game.onTap + floating "+N".
  const input = new InputManager(
    canvas,
    sceneManager.camera,
    hero,
    (info) => {
      const result = game.onTap(info);
      ui.onFirstTap();
      const sign = result.golden ? i18n.t('golden_khinkali') + ' ' : '';
      particles.spawnFloatingText(
        info.clientX,
        info.clientY,
        `+${format(result.gain, i18n.getLanguage())}`,
        result.golden
      );
      if (result.golden) {
        // Extra flair text for golden taps.
        particles.spawnFloatingText(
          info.clientX,
          info.clientY - 36,
          sign.trim(),
          true
        );
      }
    }
  );
  input.attach();

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
    // Clamp dt so tab-switch hitches or breakpoints don't fast-forward.
    if (dt > 0.1) dt = 0.1;

    game.update(dt);
    tween.update(dt);
    tower.update(dt);
    smoke.setOrigin(tower.getRoofPosition());
    smoke.update(dt);
    particles.update(dt);
    sceneManager.update(dt);

    // Hero idle bob + slow rotation.
    hero.userData.t = (hero.userData.t || 0) + dt;
    hero.position.y =
      hero.userData.baseY + Math.sin(hero.userData.t * 1.6) * 0.12;
    hero.rotation.y += dt * 0.5;

    ui.update(dt);
    sceneManager.render();
  }
  requestAnimationFrame(frame);

  // Pause loop + save when the tab is hidden; resume on return.
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
  // Surface boot failures visibly rather than failing silently.
  console.error('Khinkali Empire failed to start:', err);
  const msg = document.createElement('div');
  msg.style.cssText =
    'position:fixed;inset:0;display:grid;place-items:center;color:#fff;font-family:sans-serif;padding:20px;text-align:center;background:#2a0e12;';
  msg.textContent = 'Failed to start: ' + err.message;
  document.body.appendChild(msg);
});
