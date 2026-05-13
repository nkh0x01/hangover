// Phase 3 captures (per spec: 6 files in docs/screenshots-phase3/).
// NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots-phase3.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots-phase3';
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

  // 01 — Pricing calendar (room types × days with seeded overrides + CTAs)
  await page.goto(BASE + '/pricing/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '01-pricing-calendar.png');

  // 02 — Pricing rule create modal (Seasonal)
  await page.goto(BASE + '/pricing/rules', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button[wire\\:click="openCreate"]').click();
  await page.waitForSelector('select[wire\\:model\\.live="type"]', { state: 'visible' });
  await settle(page);
  await save(page, '02-pricing-rule-create.png');

  // 03 — Seasonal rule (switch type to seasonal so form shows valid_from/to)
  await page.selectOption('select[wire\\:model\\.live="type"]', 'seasonal');
  await page.waitForLoadState('networkidle');
  await page.locator('input[wire\\:model="name"]').fill('Summer high season');
  await page.locator('input[wire\\:model="validFrom"]').fill('2026-06-15');
  await page.locator('input[wire\\:model="validTo"]').fill('2026-09-15');
  await page.locator('input[wire\\:model="actionValue"]').fill('25');
  await settle(page);
  await save(page, '03-seasonal-rule.png');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(200);

  // 04 — Bulk update page (with a few options filled to make it useful)
  await page.goto(BASE + '/pricing/bulk', { waitUntil: 'networkidle' });
  await settle(page);
  // tick one room type
  const firstTypeBox = page.locator('input[type=checkbox][wire\\:model="roomTypeIds"]').first();
  await firstTypeBox.check();
  await page.waitForLoadState('networkidle');
  await page.locator('input[wire\\:model="value"]').fill('20');
  // tick weekends only
  await page.locator('input[wire\\:model="weekendsOnly"]').check();
  await settle(page);
  await save(page, '04-bulk-update.png');

  // 05 — Restrictions page
  await page.goto(BASE + '/pricing/restrictions', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '05-restrictions.png');

  // 06 — Reservation quote breakdown.
  // The wizard step-2 quote panel renders the rule chain immediately after
  // picking a room — capture there (simpler than driving the full 4-step
  // form in Playwright).
  await page.goto(BASE + '/reservations/create', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button[wire\\:click="nextStep"]').click();
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('button[wire\\:click^="pickRoom"]', { timeout: 15000 });
  await settle(page);
  await page.locator('button[wire\\:click^="pickRoom"]:not([disabled])').first().click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);
  await save(page, '06-reservation-quote-breakdown.png');

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
async function settle(page) { await page.waitForTimeout(500); }
async function save(page, name) {
  const file = `${OUT}/${name}`;
  await page.screenshot({ path: file, fullPage: true });
  console.log('  saved', file);
}
