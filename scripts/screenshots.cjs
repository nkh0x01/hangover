// Comprehensive UI capture for the Phase 1 reception interface.
// Run: NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots';
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
  // 1. Login (guest context)
  {
    const ctx = await browser.newContext({ viewport: DESKTOP });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await save(p, '01-login.png');
    await ctx.close();
  }

  // Authenticated pages
  const ctx = await browser.newContext({ viewport: DESKTOP });
  const page = await ctx.newPage();
  await login(page);

  // 2. Dashboard
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '02-dashboard.png');

  // 3. Calendar
  await page.goto(BASE + '/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '03-calendar.png');

  // 4. Rooms
  await page.goto(BASE + '/rooms', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '04-rooms.png');

  // 5. Reservations list
  await page.goto(BASE + '/reservations', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '05-reservations-list.png');

  // 6. Wizard step 1 — dates (uses mount() defaults: today, today+2, 2 adults)
  await page.goto(BASE + '/reservations/create', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '06-wizard-step-1-dates.png');

  await advanceWizard(page);
  // Step 2 carries a wire:click="pickRoom" button — wait for the first one.
  await page.waitForSelector('button[wire\\:click^="pickRoom"]', { timeout: 15000 });
  await settle(page);

  // 7. Wizard step 2 — room selection (initial, nothing picked)
  await save(page, '07-wizard-step-2-room.png');

  const card = page.locator('button[wire\\:click^="pickRoom"]:not([disabled])').first();
  await card.click();
  await page.waitForLoadState('networkidle');
  await settle(page);
  await save(page, '07b-wizard-step-2-room-picked.png');

  await advanceWizard(page);
  // Step 3 has the firstName input
  await page.waitForSelector('input[wire\\:model\\.live\\.debounce\\.300ms="firstName"]', { timeout: 15000 });
  await settle(page);

  // 8. Wizard step 3 — guest details
  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="firstName"]').fill('Demo');
  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="lastName"]').fill('Guest');
  await page.locator('input[wire\\:model\\.live\\.debounce\\.300ms="phone"]').fill('+995 555 000 000');
  await page.locator('input[wire\\:model="email"]').fill('demo@example.test');
  await page.locator('input[wire\\:model="country"]').fill('GE');
  await page.locator('input[wire\\:model="docNumber"]').fill('AB123456');
  await page.waitForLoadState('networkidle');
  await settle(page);
  await save(page, '08-wizard-step-3-guest.png');

  await advanceWizard(page);
  // Step 4 has wire:click="create"
  await page.waitForSelector('button[wire\\:click="create"]', { timeout: 15000 });
  await settle(page);

  // 9. Wizard step 4 — confirm
  await save(page, '09-wizard-step-4-confirm.png');

  // 10. Reservation detail in CONFIRMED state (Check in button visible)
  await page.goto(BASE + '/reservations/1', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '10-reservation-detail-confirmed.png');

  // 11. Payment modal (use r2 which is checked-in with balance remaining)
  await page.goto(BASE + '/reservations/2', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button[wire\\:click="openPaymentModal"]').click();
  await page.waitForSelector('input[wire\\:model="payAmount"]', { state: 'visible' });
  await page.waitForTimeout(400);
  await save(page, '11-payment-modal.png');

  // 12. Reservation in CHECKED_IN state — fresh page load so modal isn't open.
  await page.goto(BASE + '/reservations/2', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '12-reservation-detail-checked-in.png');

  // 13. Reservation in CHECKED_OUT state
  await page.goto(BASE + '/reservations/3', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '13-reservation-detail-checked-out.png');

  // 14. Invoice page
  await page.goto(BASE + '/invoices/1', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '14-invoice.png');

  // 18. Keyboard shortcuts overlay
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await page.keyboard.press('?');
  await page.waitForTimeout(400);
  await save(page, '18-keyboard-help.png');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(200);

  await ctx.close();
}

async function runMobile(browser) {
  const ctx = await browser.newContext({ viewport: MOBILE, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await login(page);

  // 15. Mobile dashboard
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '15-mobile-dashboard.png');

  // 16. Mobile sidebar open (hamburger)
  await page.locator('button:has-text("☰")').first().click();
  await page.waitForTimeout(400);
  await save(page, '16-mobile-sidebar-open.png');

  // close menu
  await page.locator('button:has-text("✕")').first().click();
  await page.waitForTimeout(200);

  // 17. Mobile calendar
  await page.goto(BASE + '/calendar', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '17-mobile-calendar.png');

  await ctx.close();
}

async function advanceWizard(page) {
  // Button label is localised — match by Livewire action instead.
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
