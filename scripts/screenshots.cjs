// One-shot screenshot capture for the Hotel PMS UI.
// Run: PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers node scripts/screenshots.js
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://127.0.0.1:8000';
const OUT  = 'docs/screenshots';
const EMAIL = 'admin@example.test';
const PASSWORD = 'password';

const pages = [
  { name: '01-login',                  url: '/login',                auth: false },
  { name: '02-dashboard',              url: '/dashboard',            auth: true  },
  { name: '03-calendar',               url: '/calendar',             auth: true  },
  { name: '04-rooms',                  url: '/rooms',                auth: true  },
  { name: '05-reservations-list',      url: '/reservations',         auth: true  },
  { name: '06-reservation-wizard',     url: '/reservations/create',  auth: true  },
  { name: '07-reservation-detail',     url: '/reservations/1',       auth: true  },
  { name: '08-invoice',                url: '/invoices/1',           auth: true,  optional: true },
  { name: '09-guests',                 url: '/guests',               auth: true  },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });

  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox'],
  });

  // Desktop pass
  await capture(browser, { width: 1440, height: 900, deviceScaleFactor: 2 }, '');
  // Mobile pass — just key screens
  await capture(browser, { width: 390, height: 844, deviceScaleFactor: 2 }, 'mobile-', [
    '02-dashboard', '03-calendar', '04-rooms', '07-reservation-detail',
  ]);

  await browser.close();
  console.log('done');
})();

async function capture(browser, viewport, prefix, only = null) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();

  // Login once and reuse session.
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', EMAIL);
  await page.fill('input[name=password]', PASSWORD);
  await Promise.all([
    page.waitForURL('**/dashboard'),
    page.click('button[type=submit]'),
  ]);
  console.log(prefix + 'logged in');

  for (const p of pages) {
    if (only && !only.includes(p.name)) continue;
    const file = `${OUT}/${prefix}${p.name}.png`;
    try {
      if (!p.auth) {
        // Use a fresh incognito context for /login screenshot
        const guestCtx = await browser.newContext({ viewport });
        const gp = await guestCtx.newPage();
        await gp.goto(BASE + p.url, { waitUntil: 'networkidle' });
        await gp.screenshot({ path: file, fullPage: true });
        await guestCtx.close();
      } else {
        await page.goto(BASE + p.url, { waitUntil: 'networkidle' });
        // wait for Livewire to settle
        await page.waitForTimeout(400);
        await page.screenshot({ path: file, fullPage: true });
      }
      console.log('  saved', file);
    } catch (e) {
      if (p.optional) { console.log('  skipped', file, e.message); continue; }
      throw e;
    }
  }

  await context.close();
}
