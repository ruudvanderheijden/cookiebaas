/**
 * End-to-end smoketest voor Cookiebaas (optioneel — vereist Playwright + Chrome).
 *
 * Draait de echte consent-flow in een browser tegen een live of staging-site
 * en controleert de invarianten die we deze week hebben vastgelegd:
 *
 *   1. Vers bezoek zonder keuze  -> GTM laadt, GA stuurt alleen cookieloze
 *                                    pings (gcs=G100), GEEN _ga-cookies.
 *   2. Na akkoord                -> _ga-cookies verschijnen, GA vuurt volledig.
 *   3. Na weigeren (na akkoord)  -> _ga-cookies weg en blijven weg, geen G111.
 *
 * Gebruik:
 *   npm i -D playwright   (eenmalig, buiten de plugin — niet meegeleverd in de dist)
 *   CM_SMOKE_URL=https://staging.voorbeeld.nl/ node tests/smoke.mjs
 *
 * Deze test hoort NIET in de distributie-zip en NIET in de PHP-runner; het is
 * een handmatige/CI-controle tegen een echte site.
 */

import { chromium } from 'playwright';

const URL = process.env.CM_SMOKE_URL || process.argv[2];
if (!URL) {
  console.error('Geef een URL: CM_SMOKE_URL=https://... node tests/smoke.mjs');
  process.exit(2);
}

let fails = 0;
const ok = (label, cond) => {
  console.log(`  ${cond ? '\x1b[32mPASS\x1b[0m' : '\x1b[31mFAIL\x1b[0m'}  ${label}`);
  if (!cond) fails++;
};
const gcsOf = (u) => new globalThis.URL(u).searchParams.get('gcs');
const gaNames = (cookies) => cookies.map((c) => c.name).filter((n) => /^_ga|^_gcl|^_gid/.test(n));

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext();
const page = await ctx.newPage();

const hits = [];
page.on('request', (r) => { if (/\/g\/collect/.test(r.url())) hits.push(gcsOf(r.url())); });

try {
  // 1. Vers bezoek, geen keuze
  console.log(`\n== 1. Vers bezoek zonder consent — ${URL} ==`);
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(2500);

  const html = await page.content();
  ok('draait cache-veilige versie (client-side cookie-lezer)', /if \(!c\) return;/.test(html));
  ok('geen ingebakken accept-all in de HTML', !/cm_method['"]?\s*:\s*['"]accept-all/.test(html));

  const gtmLoaded = await page.evaluate(() => typeof window.google_tag_manager !== 'undefined');
  ok('GTM-container geladen vóór consent', gtmLoaded);
  ok('GA stuurt alleen cookieloze pings (gcs=G100)', hits.length === 0 || hits.every((g) => g === 'G100'));
  ok('geen _ga/_gcl-cookies zonder consent', gaNames(await ctx.cookies()).length === 0);

  // 2. Akkoord
  console.log('\n== 2. Na akkoord ==');
  hits.length = 0;
  const accept = page.locator('#cm-btn-accept');
  if (await accept.count()) {
    await accept.click();
    await page.waitForTimeout(7000); // GA batcht de eerste hit ~5s na de keuze
    const after = gaNames(await ctx.cookies());
    ok('_ga-cookies verschijnen na akkoord', after.length > 0);
    ok('GA vuurt volledig (gcs=G111)', hits.some((g) => g === 'G111'));
  } else {
    ok('accept-knop (#cm-btn-accept) gevonden', false);
  }

  // 3. Weigeren na akkoord
  console.log('\n== 3. Na weigeren (intrekking) ==');
  hits.length = 0;
  await page.evaluate(() => window.Cookiebaas && window.Cookiebaas.openPrefs && window.Cookiebaas.openPrefs());
  await page.waitForTimeout(800);
  const reject = page.locator('#cm-btn-rejectall, #cm-prefs .cm-btn-outline').first();
  if (await reject.count()) {
    await reject.click();
    await page.waitForTimeout(7000); // incl. herlaad; ruim voorbij het GA-batchvenster
    const after = gaNames(await ctx.cookies());
    ok('_ga/_gcl-cookies verwijderd na weigeren', after.length === 0);
    ok('geen G111-hits meer ná de weigering', !hits.includes('G111'));
  } else {
    ok('weiger-knop gevonden', false);
  }
} finally {
  await browser.close();
}

console.log('\n' + (fails ? `\x1b[31m✗ ${fails} gefaald\x1b[0m` : '\x1b[32m✓ Smoketest groen\x1b[0m'));
process.exit(fails ? 1 : 0);
