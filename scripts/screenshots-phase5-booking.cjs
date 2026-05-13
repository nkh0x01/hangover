// Phase 5 captures — 9 screenshots in docs/screenshots-phase5-booking/.
// NODE_PATH=/opt/node22/lib/node_modules node scripts/screenshots-phase5-booking.cjs
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots-phase5-booking';
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

  // Resolve the seeded Booking sandbox connection id.
  await page.goto(BASE + '/channels/booking', { waitUntil: 'networkidle' });
  await settle(page);

  // 01 — Booking connection list
  await save(page, '01-booking-connection.png');

  const openHref = await page.locator('a:has-text("გახსნა")').first().getAttribute('href');
  const url = openHref ? new URL(openHref, BASE).pathname : '/channels/booking/1';

  // 07 — Dry-run warning (captured early while dry-run is still on)
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '07-dry-run-warning.png');

  // 02 — Credentials page
  await page.goto(BASE + url + '/credentials', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '02-booking-credentials.png');

  // 03 — Test connection: click and capture
  await page.goto(BASE + url + '/test', { waitUntil: 'networkidle' });
  await settle(page);
  await page.locator('button:has-text("ტესტის გაშვება")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await save(page, '03-test-connection.png');

  // 04 — Preview availability payload (default)
  await page.goto(BASE + url + '/preview-payload', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '04-preview-availability-payload.png');

  // 05 — Preview rates payload
  await page.selectOption('select[wire\\:model\\.live="kind"]', 'rates');
  await page.waitForLoadState('networkidle');
  await settle(page);
  await save(page, '05-preview-rates-payload.png');

  // 06 — Sync logs (run a couple of dry-run pushes first to populate)
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.locator('button:has-text("ხელმისაწვდომობის გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.locator('button:has-text("ტარიფების გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.locator('button:has-text("შეზღუდვების გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.goto(BASE + url + '/logs', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '06-sync-logs.png');

  // 08 — Conflict detected: visit the global conflicts page (Phase 4)
  // We pre-seed one inbound Booking webhook that conflicts.
  await postSignedConflictWebhook(page);
  await page.goto(BASE + '/channels/conflicts', { waitUntil: 'networkidle' });
  await settle(page);
  await save(page, '08-conflict-detected.png');

  // 09 — Live mode confirmation modal
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await settle(page);
  // Switch to LIVE mode
  await page.locator('button:has-text("LIVE რეჟიმზე გადასვლა")').click();
  await page.waitForLoadState('networkidle');
  await settle(page);
  // Click a push to open the confirm modal
  await page.locator('button:has-text("ხელმისაწვდომობის გაგზავნა")').click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await save(page, '09-live-mode-confirmation.png');

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

// Drive the webhook receiver with a conflict-inducing payload (rooms blocked
// by direct booking). We use page.request to keep the auth cookie context.
async function postSignedConflictWebhook(page) {
  // Find Booking connection id from the URL we computed earlier.
  // (Re-parse by visiting the index — keeps this helper self-contained.)
  await page.goto(BASE + '/channels/booking', { waitUntil: 'networkidle' });
  const openHref = await page.locator('a:has-text("გახსნა")').first().getAttribute('href');
  const id = openHref ? openHref.split('/').pop() : '1';

  // We don't actually call the webhook here (signature would need the secret
  // and HMAC). Instead, we render the conflict from the seeded mock data —
  // /channels/conflicts already shows that row, which is enough for the
  // screenshot.
  return null;
}
