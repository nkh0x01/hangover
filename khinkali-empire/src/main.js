// main.js
// Bootstrap and the single requestAnimationFrame loop.
// Boot order: Localization -> Scene -> tower/ornaments/steam -> GameManager
// (load + offline) -> UIManager -> InputManager -> rAF loop.

import './styles/main.css';
import * as THREE from 'three';

import langData from './i18n/lang.json';
import { i18n } from './i18n/LocalizationManager.js';

import { SceneManager } from './world/SceneManager.js';
import { TowerController } from './world/TowerController.js';
import { SteamSystem } from './world/SteamSystem.js';

import { GameManager } from './core/GameManager.js';
import { ParticleSystem } from './fx/ParticleSystem.js';
import { InputManager } from './fx/InputManager.js';
import { tweenRunner } from './fx/Tween.js';

import { UIManager } from './ui/UIManager.js';

function boot() {
  // 1) Localization from bundled JSON, before any UI renders.
  i18n.init(langData);
  document.documentElement.setAttribute('lang', i18n.getLanguage());

  // 2) Scene.
  const canvas = document.getElementById('game-canvas');
  const sceneMgr = new SceneManager(canvas).init();

  // 3) Tower + ornaments + steam.
  const tower = new TowerController(sceneMgr.scene).build();
  const steam = new SteamSystem(sceneMgr.scene);
  tower.group.updateMatrixWorld(true);
  steam.setOrigin(tower.getChimneyWorldPosition(new THREE.Vector3()));

  // 4) Game state (load save + offline earnings).
  const game = new GameManager().init();
  tower.setStageInstant(game.state.totalGoldEarned);

  // 5) FX + input.
  const particles = new ParticleSystem();
  const input = new InputManager().attach(canvas, sceneMgr.camera);

  // 6) UI.
  const ui = new UIManager(game, i18n, input, particles).init();

  // Tapping the 3D tower triggers an assemble, just like the central button.
  input.setTowerTarget(tower.group, (x, y) => ui.prep._onAssembleTap(x, y));

  // ---- autosave on background / page hide --------------------------------
  const persist = () => game.persist();
  window.addEventListener('visibilitychange', () => {
    if (document.hidden) persist();
  });
  window.addEventListener('pagehide', persist);
  window.addEventListener('beforeunload', persist);

  // ---- main loop ---------------------------------------------------------
  let last = performance.now();
  let paused = false;

  function frame(now) {
    requestAnimationFrame(frame);
    if (paused) {
      last = now;
      return;
    }
    let dt = (now - last) / 1000;
    last = now;
    // Clamp dt so a long stall (tab throttle) doesn't dump a huge chunk at once;
    // genuine offline time is handled separately on load.
    if (dt > 0.1) dt = 0.1;
    if (dt <= 0) return;

    // Core: recompute derived values, then apply the production tick.
    game.refresh();
    game.tick(dt);

    // World evolution + ambient.
    tower.updateStage(game.state.totalGoldEarned, true);
    const intensity = game.getIntensity();
    steam.setIntensity(intensity);
    sceneMgr.setGlow(intensity);

    // Update animation systems.
    tweenRunner.update(dt);
    sceneMgr.update(dt);
    tower.update(dt);
    steam.update(dt);
    particles.update(dt);
    ui.update(dt);

    sceneMgr.render();
  }

  // Pause the loop while hidden; resume cleanly on visibility.
  document.addEventListener('visibilitychange', () => {
    paused = document.hidden;
    if (!paused) last = performance.now();
  });

  requestAnimationFrame(frame);
}

// Boot once the DOM is ready.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
