# ხინკლის იმპერია · Khinkali Empire

A mobile-first **3D idle / clicker** game built with **Three.js** + **Vite**.
Tap the floating khinkali to earn gold, buy producers that generate gold per
second, watch a procedural Khevsureti stone tower evolve into a fortified
mountain empire, and prestige (“Rebake”) for permanent multipliers.

> No external 3D models or images — every mesh, ornament, and the sky are
> generated procedurally at runtime.

## Run it

```bash
npm install
npm run dev      # start the dev server (open the printed localhost URL)
```

Other scripts:

```bash
npm run build    # production build into dist/
npm run preview  # preview the production build
```

The build is mobile-first **portrait**. On desktop the play area is centered in
a phone-width column (max ~480px) with neutral gutters. For the best feel, open
your browser dev tools and switch to a phone viewport.

## Controls

- **Tap the central khinkali** → earn gold, spawn 3D khinkali + floating “+N”
  particles, squish-stretch the khinkali, bounce the tower. Occasionally a
  **golden khinkali** tap is worth 7×.
- **Bottom sheet (Shop)** → drag or tap the handle to slide it up/down.
  - **Producers** tab: buy generators that add gold-per-second.
  - **Tap power** tab: raise gold-per-tap.
  - **x1 / x10 / Max** buy-mode buttons.
- **⚙️ Settings** (top-right) → switch language (KA / EN / RU / DE), view stats,
  or erase your save.
- **Rebake** (bottom of the sheet) → soft-reset for permanent **Prestige
  Points** (+2% global production each), unlockable at 1,000,000 total gold.

Progress autosaves every 10s and on tab hide; offline earnings (capped at 8h)
are granted on return.

## Architecture

```
khinkali-empire/
├── index.html                # single screen: canvas + DOM overlay
├── public/lang/lang.json     # KA / EN / RU / DE translations
└── src/
    ├── main.js               # bootstrap + single rAF loop
    ├── core/
    │   ├── GameManager.js     # state, tick (gold += kps*dt), taps, prestige
    │   ├── Economy.js         # cost/KPS math + big-number formatting
    │   └── SaveSystem.js      # localStorage + offline earnings
    ├── ui/
    │   ├── UIManager.js       # HUD, shop, modals, i18n binding
    │   └── BottomSheet.js     # slide-up/down sheet controller
    ├── i18n/
    │   └── LocalizationManager.js   # fetch lang.json, t(), live re-bind
    ├── world/
    │   ├── SceneManager.js    # renderer, camera, lights, dusk sky, fog
    │   ├── TowerController.js  # procedural tower + 5 evolution stages
    │   ├── OrnamentFactory.js  # Borjgali + geometric trim (procedural)
    │   └── SmokeSystem.js      # additive roof smoke, intensity scales w/ KPS
    ├── fx/
    │   ├── InputManager.js     # pointer/touch raycasting (mobile-safe)
    │   ├── ParticleSystem.js   # pooled 3D khinkali pops + "+N" labels
    │   └── Tween.js            # lerp/easing + squish-stretch helper
    ├── data/upgrades.js        # generator + tap-power definitions
    └── styles/main.css         # mobile UI, safe areas, bottom sheet
```

### How the loop fits together

`main.js` runs one `requestAnimationFrame` loop with delta time:

1. `GameManager.update(dt)` — passive income, stage checks, autosave.
2. `TweenManager.update(dt)` — all active tweens (squish, recoil, stage pops).
3. `TowerController.update(dt)` — Borjgali spin, lanterns, banners.
4. `SmokeSystem.update(dt)` — roof smoke (intensity tied to evolution stage).
5. `ParticleSystem.update(dt)` — pooled tap FX.
6. `SceneManager.update(dt)` + `render()` — camera drift + draw.
7. `UIManager.update(dt)` — HUD counters + live affordability.

The loop pauses when `document.hidden` is true and resumes on return; pixel
ratio is capped at 2 for performance.

### Economy

- Generator cost: `ceil(baseCost * 1.15^owned)`; bulk buys use the geometric
  series and a “max affordable” solver.
- `totalKPS = Σ(owned * baseKps) * globalMultiplier`, where
  `globalMultiplier = 1 + 0.02 * prestigePoints`.
- `goldPerTap = (baseTap + Σ tap bonuses) * globalMultiplier`.
- Numbers format as thousands separators below 1e6, then `K, M, B, T, aa, ab…`.

### Localization

All user-facing strings come from `public/lang/lang.json` via `data-i18n` /
`data-i18n-attr` bindings. Switching language re-scans and updates every bound
node live — no reload. Default language is detected from `navigator.language`
with a fallback chain to Georgian (KA), and the choice is persisted.

## Mobile shell (Capacitor-ready)

Client code uses no Node-only APIs and `vite.config.js` sets `base: './'`, so
the produced `dist/` works inside a Capacitor webview. To wrap later:

```bash
npm run build
npx cap init "Khinkali Empire" ge.khinkali.empire --web-dir dist
npx cap add android   # and/or: npx cap add ios
npx cap sync
```
