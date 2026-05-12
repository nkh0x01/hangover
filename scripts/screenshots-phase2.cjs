// Phase 2 inventory/POS captures.
// Run: NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots-phase2.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots-phase2';
const EMAIL = 'admin@example.test';
const PASSWORD = 'password';
const DESKTOP = { width: 1440, height: 900, deviceScaleFactor: 2 };

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox'],
  });

  const ctx = await browser.newContext({ viewport: DESKTOP });
  const page = await ctx.newPage();
  await login(page);
  await page.goto(BASE + '/locale/ka', { waitUntil: 'networkidle' });

  // 01 — Products
  await page.goto(BASE + '/products', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '01-products.png');

  // 02 — Inventory dashboard
  await page.goto(BASE + '/inventory', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '02-inventory-dashboard.png');

  // 03 — Stock movements ledger
  await page.goto(BASE + '/inventory/movements', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '03-stock-movements.png');

  // 04 — Minibar setup (overview grid)
  await page.goto(BASE + '/inventory/minibars', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '04-minibar-setup.png');

  // 05 — Per-room minibar editor (room 1 = "101")
  await page.goto(BASE + '/rooms/1/minibar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '05-room-minibar.png');

  // 06 — POS
  await page.goto(BASE + '/inventory/pos', { waitUntil: 'networkidle' });
  await settle(page);
  // Add two products to the cart for a meaningful screenshot.
  const cards = page.locator('button[wire\\:click^="addToCart"]:not([disabled])');
  if (await cards.count() >= 2) {
    await cards.nth(0).click();
    await page.waitForLoadState('networkidle');
    await cards.nth(0).click();
    await page.waitForLoadState('networkidle');
    await cards.nth(1).click();
    await page.waitForLoadState('networkidle');
    await settle(page);
  }
  await save(page, '06-pos.png');

  // 07 — Reservation detail with a minibar charge applied (reservation #2 is checked-in)
  // First sell a product to that reservation via the page.
  await page.goto(BASE + '/reservations/2', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button[wire\\:click="openSellModal"]').click();
  await page.waitForSelector('select[wire\\:model="sellProductId"]', { state: 'visible' });
  await settle(page);
  // pick the first non-empty option
  const options = page.locator('select[wire\\:model="sellProductId"] option');
  const count = await options.count();
  for (let i = 1; i < count; i++) {
    const v = await options.nth(i).getAttribute('value');
    if (v) {
      await page.selectOption('select[wire\\:model="sellProductId"]', v);
      break;
    }
  }
  await page.locator('input[wire\\:model="sellQuantity"]').fill('2');
  await page.waitForLoadState('networkidle');
  await page.locator('button[wire\\:click="sellProduct"]').click();
  await page.waitForLoadState('networkidle');
  await settle(page);
  await save(page, '07-reservation-with-minibar-charges.png');

  await browser.close();
  console.log('done');
})();

async function login(page) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', EMAIL);
  await page.fill('input[name=password]', PASSWORD);
  await Promise.all([
    page.waitForURL('**/dashboard'),
    page.click('button[type=submit]'),
  ]);
}

async function settle(page) {
  await page.waitForTimeout(450);
}

async function save(page, name) {
  const file = `${OUT}/${name}`;
  await page.screenshot({ path: file, fullPage: true });
  console.log('  saved', file);
}
