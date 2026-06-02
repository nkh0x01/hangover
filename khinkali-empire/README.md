# ხინკლის იმპერია · Khinkali Empire

A mobile-first 3D **idle / assembly-line tycoon**. Tap to assemble khinkali,
automate a 5-step kitchen production chain gated by its weakest **bottleneck**,
grow a 3D Khevsureti **sacred-factory tower** that evolves across five stages,
and raise capital by **selling equity** to virtual investors — all on a single
portrait screen.

Built with **Vite** + **three.js** (vanilla ES modules, no framework).
Languages: ქართული · English · Русский · Deutsch (live-switchable).

## Run locally

```bash
npm install
npm run dev      # http://localhost:5173
```

## Build (GitHub Pages ready)

```bash
npm run build    # outputs dist/ with RELATIVE asset paths (base: './')
npm run preview  # preview the production build locally
```

The build uses `base: './'`, so `dist/` works both when opened directly and
under any GitHub Pages sub-path (e.g. `https://<user>.github.io/<repo>/`).

## Deploy to GitHub Pages

This repo ships a workflow at `.github/workflows/deploy.yml` that builds the
`khinkali-empire/` project and publishes `dist/` to GitHub Pages.

1. In your repository: **Settings → Pages → Build and deployment →
   Source: GitHub Actions**.
2. Push to `main` / `master` (or the configured feature branch). The workflow
   builds and deploys automatically. You can also trigger it manually from the
   **Actions** tab (*workflow_dispatch*).

## How the game works

### Production chain (bottleneck model)
Per khinkali recipe: `1 Flour, 1 Meat, 0.5 Herbs`.

- **Ingredient-limited rate** = `min(flour/1, meat/1, herbs/0.5)`
- **Machine-limited rate** = `min(dough, meat, herb, pleat, boil)` stations
- **autoKPS** = `min(ingredientRate, machineRate) × globalMultiplier`
- **Passive gold/sec** = `autoKPS × goldPerKhinkali × ownerShare`
- **Manual tap** = `goldPerKhinkali × globalMultiplier` (never reduced by equity)

The on-screen **bottleneck badge** always names the limiting step and the
cheapest thing to upgrade next. Costs grow as `baseCost × 1.15^level`;
buy in **x1 / x10 / Max**.

### Tiers & tech
- **Tier 1** — Dough Mixer, Meat Grinder, Herb Chopper (stations)
- **Tier 2** — Pleating Robot, Steam Cauldron (stations), Auto Seller
  (+10% gold/khinkali per level), Conveyor Belt (+5% global per level)
- **Tier 3** — Full Assembly Line, Robotic Kitchen (multiply machine rate;
  intentionally expensive — financed via investors)

### Shares & investments
Sell equity to investors for an instant lump sum at the permanent cost of a
lower passive multiplier (`ownerShare`). Valuation =
`grossRevenue/sec × 600`. Equity caps at **80%**; each investor has a cooldown
(clamped to real elapsed time on load). **Buy back** equity later at a
`×1.5` premium once your passive income dwarfs the valuation.

### Tower evolution
The Khevsureti tower evolves across 5 stages keyed to total gold earned
(0 → 5K → 100K → 5M → 500M), gaining outbuildings, a second tower, walls,
lanterns, banners and a fortified golden "empire". Roof **steam intensity
scales with KPS**, and the gold **Borjgali** sun rotates continuously.

### Persistence
Autosaves every 10s and on background/hide. Grants **offline earnings**
(capped at 8h) with a *welcome back* modal. Reset from **Settings ⚙️**.

## Project structure

```
khinkali-empire/
├── index.html
├── package.json
├── vite.config.js
├── .github/workflows/deploy.yml
├── src/
│   ├── main.js
│   ├── core/      GameManager · Economy · SharesSystem · SaveSystem
│   ├── ui/        UIManager · PrepTable · ShopSheet · SharesPanel
│   ├── i18n/      LocalizationManager · lang.json (KA/EN/RU/DE)
│   ├── world/     SceneManager · TowerController · OrnamentFactory · SteamSystem
│   ├── fx/        InputManager · ParticleSystem · Tween
│   ├── data/      ingredients · gadgets · investors
│   └── styles/    main.css
└── public/icons/  favicon
```

## Controls
- **Tap the central khinkali** (or the 3D tower) to assemble & sell manually.
- **Tap ingredient slots** for a juicy buffer bump.
- **Drag the bottom handle** (or tap it) to open the shop; two tabs:
  *Ingredients & Tech* and *Shares & Investments*.
- **⚙️** opens settings & language.
