// Phase 4 captures — 7 screenshots in docs/screenshots-phase4/.
// NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots-phase4.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots-phase4';
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

  // 01 — Channels list
  await page.goto(BASE + '/channels', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '01-channels-list.png');

  // Pick the row whose channel cell reads "Mock" (the seeded sandbox with data).
  const mockRow = page.locator('tr', { hasText: 'Mock' });
  const mockHref = await mockRow.locator('a:has-text("გახსნა")').first().getAttribute('href');
  const connectionUrl = mockHref ? new URL(mockHref, BASE).pathname : '/channels/1';

  // 02 — Channel detail
  await page.goto(BASE + connectionUrl, { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '02-channel-detail.png');

  // 03 — Room mappings
  await page.goto(BASE + connectionUrl + '/mappings', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '03-room-mappings.png');

  // 04 — Rate mappings (same page, scrolled to right column on smaller widths)
  // We took the full page above already; capture again with focus on rate side.
  await save(page, '04-rate-mappings.png');

  // 05 — Sync logs (run a pull first so the log has rich entries).
  await page.goto(BASE + connectionUrl, { waitUntil: 'networkidle' });
  await page.locator('button:has-text("ჯავშნების ჩამოწერა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.locator('button:has-text("ხელმისაწვდომობის გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.locator('button:has-text("ტარიფების გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.goto(BASE + connectionUrl + '/logs', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '05-sync-logs.png');

  // 06 — Conflict resolution (force a conflict via the seeder, then capture).
  // The mock-seeded inbox is reusable: after the pull above some rows may be processed,
  // others stay conflict if the rooms are booked. If there are no conflicts now,
  // we just capture the empty state, which is also informative.
  await page.goto(BASE + '/channels/conflicts', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '06-conflict-resolution.png');

  // 07 — Manual sync controls captured on the detail page after toasts settled.
  await page.goto(BASE + connectionUrl, { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '07-manual-sync.png');

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
