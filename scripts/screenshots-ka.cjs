// Georgian-locale capture for Phase 1 UI.
// Writes to docs/screenshots-ka/ with -ka suffix filenames per spec.
// Run: NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots-ka.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots-ka';
const EMAIL = 'admin@example.test';
const PASSWORD = 'password';

const DESKTOP = { width: 1440, height: 900, deviceScaleFactor: 2 };
const MOBILE  = { width: 390,  height: 844, deviceScaleFactor: 2 };

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox'],
  });

  await runDesktop(browser);
  await runMobile(browser);

  await browser.close();
  console.log('done');
})();

async function runDesktop(browser) {
  // 01 — Login (fresh guest context, locale defaults to ka)
  {
    const ctx = await browser.newContext({ viewport: DESKTOP });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await save(p, '01-login-ka.png');
    await ctx.close();
  }

  // Auth'd
  const ctx = await browser.newContext({ viewport: DESKTOP });
  const page = await ctx.newPage();
  await login(page);
  // Force ka in case the seeded user locale is overridden.
  await page.goto(BASE + '/locale/ka', { waitUntil: 'networkidle' });

  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '02-dashboard-ka.png');

  await page.goto(BASE + '/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '03-calendar-ka.png');

  await page.goto(BASE + '/rooms', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '04-rooms-ka.png');

  await page.goto(BASE + '/reservations', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '05-reservations-list-ka.png');

  // Wizard steps 1-4
  await page.goto(BASE + '/reservations/create', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '06-wizard-step-1-ka.png');

  await advanceWizard(page);
  await page.waitForSelector('button[wire\\:click^="pickRoom"]', { timeout: 15000 });
  await settle(page);
  await save(page, '07-wizard-step-2-ka.png');

  // pick first available room to show the quote panel
  await page.locator('button[wire\\:click^="pickRoom"]:not([disabled])').first().click();
  await page.waitForLoadState('networkidle');
  await settle(page);

  await advanceWizard(page);
  await page.waitForSelector('input[wire\\:model\\.live\\.debounce\\.300ms="firstName"]', { timeout: 15000 });
  await settle(page);

  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="firstName"]').fill('დემო');
  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="lastName"]').fill('სტუმარი');
  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="phone"]').fill('+995 555 000 000');
  await page.locator('input[wire\\:model="email"]').fill('demo@example.test');
  await page.locator('input[wire\\:model="country"]').fill('GE');
  await page.locator('input[wire\\:model="docNumber"]').fill('AB123456');
  await page.waitForLoadState('networkidle');
  await settle(page);
  await save(page, '08-wizard-step-3-ka.png');

  await advanceWizard(page);
  await page.waitForSelector('button[wire\\:click="create"]', { timeout: 15000 });
  await settle(page);
  await save(page, '09-wizard-step-4-ka.png');

  // 10 — Reservation detail (confirmed state — Check-in button visible)
  await page.goto(BASE + '/reservations/1', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '10-reservation-detail-ka.png');

  // 11 — Payment modal (r2 is checked-in with balance)
  await page.goto(BASE + '/reservations/2', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button[wire\\:click="openPaymentModal"]').click();
  await page.waitForSelector('input[wire\\:model="payAmount"]', { state: 'visible' });
  await page.waitForTimeout(400);
  await save(page, '11-payment-modal-ka.png');

  // 12 — Invoice page
  await page.goto(BASE + '/invoices/1', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '12-invoice-ka.png');

  // 15 — Keyboard help overlay
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await page.keyboard.press('?');
  await page.waitForTimeout(400);
  await save(page, '15-keyboard-help-ka.png');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(200);

  await ctx.close();
}

async function runMobile(browser) {
  const ctx = await browser.newContext({ viewport: MOBILE, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await login(page);
  await page.goto(BASE + '/locale/ka', { waitUntil: 'networkidle' });

  // 13 — Mobile dashboard
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '13-mobile-dashboard-ka.png');

  // 14 — Mobile calendar
  await page.goto(BASE + '/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '14-mobile-calendar-ka.png');

  await ctx.close();
}

async function advanceWizard(page) {
  await page.locator('button[wire\\:click="nextStep"]').click();
  await page.waitForLoadState('networkidle');
}

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
