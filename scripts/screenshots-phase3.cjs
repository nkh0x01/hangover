// Phase 3 pricing captures.
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

  // 01 Pricing rules list
  await page.goto(BASE + '/pricing/rules', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '01-pricing-rules.png');

  // 02 New rule modal
  await page.locator('button[wire\\:click="openCreate"]').click();
  await page.waitForSelector('select[wire\\:model\\.live="type"]', { state: 'visible' });
  await settle(page);
  await save(page, '02-pricing-rule-new.png');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(200);

  // 03 Pricing calendar
  await page.goto(BASE + '/pricing/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '03-pricing-calendar.png');

  // 04 Cell editor open
  // pick the first cell that has wire:click="edit("
  const cell = page.locator('button[wire\\:click^="edit("]').first();
  await cell.click();
  await page.waitForSelector('input[wire\\:model="editPrice"]', { state: 'visible' });
  await settle(page);
  await save(page, '04-pricing-calendar-edit.png');

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

async function settle(page) { await page.waitForTimeout(450); }
async function save(page, name) {
  const file = `${OUT}/${name}`;
  await page.screenshot({ path: file, fullPage: true });
  console.log('  saved', file);
}
