/**
 * Kontrola stránek lokální kopie webu před vydáním.
 *
 *   npm test                        výchozí adresa http://localhost:8321
 *   BASE=https://… npm test         jiný web (jen čtení, nic se neodesílá)
 *   CHROME_PATH=/cesta npm test     jiný prohlížeč
 *
 * Kontroluje: stavové kódy, chyby v konzoli a JS, přetékání stránky do strany,
 * prezentaci bannerů, dlaždice s firmami, přepínač jazyků, bezpečnostní hlavičky,
 * SEO značky, /llms.txt a cookie lištu (jen když je vyplněné ID GA4; Google se neosloví).
 * Snímky ukládá do out/. Při chybě skončí s kódem 1.
 */
import { chromium } from 'playwright-core';
import { existsSync, mkdirSync } from 'node:fs';

const BASE = (process.env.BASE || 'http://localhost:8321').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH || [
	'/Applications/Chromium.app/Contents/MacOS/Chromium',
	'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	'/usr/bin/chromium',
	'/usr/bin/chromium-browser',
	'/usr/bin/google-chrome',
].find((p) => existsSync(p));

const PAGES = [
	{ path: '/', name: 'uvod-cs', status: 200, home: true },
	{ path: '/en/', name: 'uvod-en', status: 200, home: true },
	{ path: '/ahoj-vsichni/', name: 'prispevek', status: 200 },
	{ path: '/stranka-ktera-neexistuje/', name: '404', status: 404 },
];
const VIEWPORTS = [
	{ name: 'desktop', width: 1400, height: 900 },
	{ name: 'mobil', width: 390, height: 844, isMobile: true, hasTouch: true },
];

const failures = [];
const fail = (msg) => { failures.push(msg); console.log(`  ✗ ${msg}`); };
const ok = (msg) => console.log(`  ✓ ${msg}`);

if (!CHROME) {
	console.error('Nenašel jsem Chromium ani Chrome – nastavte CHROME_PATH.');
	process.exit(2);
}
mkdirSync(new URL('./out/', import.meta.url), { recursive: true });

const browser = await chromium.launch({ executablePath: CHROME });

try {
	for (const vp of VIEWPORTS) {
		const context = await browser.newContext({
			viewport: { width: vp.width, height: vp.height },
			isMobile: !!vp.isMobile,
			hasTouch: !!vp.hasTouch,
			reducedMotion: 'no-preference',
		});
		await context.addInitScript(() => {
			try { localStorage.setItem('mndConsent', JSON.stringify({ v: 'denied', t: Date.now() })); } catch (e) {}
		});

		for (const pg of PAGES) {
			const label = `${vp.name} ${pg.path}`;
			console.log(`\n${label}`);
			const page = await context.newPage();
			try {
			const errors = [];
			page.on('console', (m) => {
				// Stránka 404 sama vrací stav 404 – Chrome to hlásí jako chybu načtení dokumentu.
				const own = m.location().url === BASE + pg.path && pg.status === 404;
				if (m.type() === 'error' && !own) errors.push(m.text());
			});
			page.on('pageerror', (e) => errors.push(e.message));

			const response = await page.goto(BASE + pg.path, { waitUntil: 'load' });
			const status = response ? response.status() : 0;
			status === pg.status ? ok(`HTTP ${status}`) : fail(`${label}: HTTP ${status}, čekáno ${pg.status}`);

			const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
			overflow <= 0 ? ok('bez přetékání do strany') : fail(`${label}: stránka přetéká o ${overflow} px`);

			if (pg.home) {
				const hero = await page.evaluate(() => {
					const root = document.querySelector('.mnd-hero');
					if (!root) return null;
					return {
						carousel: root.classList.contains('mnd-hero--carousel'),
						ready: root.classList.contains('is-ready'),
						slides: root.querySelectorAll('.mnd-hero__slide').length,
						active: root.querySelectorAll('.mnd-hero__slide.is-active').length,
						dots: root.querySelectorAll('.mnd-hero__dot').length,
					};
				});
				if (!hero) {
					fail(`${label}: chybí bannery`);
				} else if (hero.carousel) {
					hero.ready && hero.active === 1 && hero.dots === hero.slides
						? ok(`prezentace: ${hero.slides} bannerů, ${hero.dots} teček`)
						: fail(`${label}: prezentace neběží (${JSON.stringify(hero)})`);
					// Přepnutí na další banner – šipkou, na úzkém displeji (šipky skryté) tečkou.
					const next = page.locator('[data-mnd-carousel-next]');
					const byArrow = await next.isVisible();
					await (byArrow ? next : page.locator('.mnd-hero__dot').nth(1)).click();
					const second = await page.evaluate(() => [...document.querySelectorAll('.mnd-hero__slide')].findIndex((s) => s.classList.contains('is-active')));
					const how = byArrow ? 'šipka „další“' : 'druhá tečka';
					second === 1 ? ok(`${how} přepne banner`) : fail(`${label}: ${how} nepřepnula banner (aktivní ${second})`);
				} else {
					ok(`bannery pod sebou: ${hero.slides}`);
				}

				const tiles = await page.locator('.mnd-segments__item').count();
				tiles === 5 ? ok('5 dlaždic') : fail(`${label}: dlaždic ${tiles}, čekáno 5`);

				// Rozbalení seznamu firem u dlaždice bez odkazu (Vrtný kontraktor / Drilling & Services).
				const trigger = page.locator('.mnd-segments__trigger').first();
				if (await trigger.count()) {
					await trigger.click();
					const visible = await page.locator('.mnd-segments__item.is-open .mnd-segments__submenu').isVisible();
					visible ? ok('seznam firem se rozbalí') : fail(`${label}: seznam firem se nerozbalil`);
				}

				// Česká typografie (inc/typography.php): pevná mezera za jednopísmennou předložkou, v angličtině ne.
				const nbsp = await page.evaluate(() => /(^|[\s(„])[ksvz]\u00a0/i.test(document.body.innerText));
				const czech = pg.path === '/';
				nbsp === czech ? ok(czech ? 'pevné mezery za předložkami' : 'v angličtině bez českých pevných mezer') : fail(`${label}: pevné mezery za předložkami ${nbsp ? 'jsou' : 'chybí'}`);

				const langs = await page.locator('.mnd-lang li').count();
				langs === 2 ? ok('přepínač jazyků CS/EN') : fail(`${label}: přepínač jazyků má ${langs} položek`);
			}

			if (vp.name === 'desktop' && pg.path === '/') {
				const headers = response.headers();
				for (const h of ['x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy']) {
					headers[h] ? ok(`hlavička ${h}`) : fail(`${label}: chybí hlavička ${h}`);
				}
				headers['x-pingback'] ? fail(`${label}: hlavička X-Pingback by neměla být`) : ok('bez X-Pingback');

				const seo = await page.evaluate(() => ({
					description: document.querySelectorAll('meta[name="description"]').length,
					og: document.querySelectorAll('meta[property="og:title"]').length,
					image: document.querySelector('meta[property="og:image"]')?.content || '',
					ld: [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => { try { return JSON.parse(s.textContent)['@graph'].map((g) => g['@type']).join('+'); } catch (e) { return 'chyba'; } }),
				}));
				seo.description === 1 && seo.og === 1 ? ok('meta description a Open Graph (každé jednou)') : fail(`${label}: description ${seo.description}×, og:title ${seo.og}×`);
				seo.ld.join() === 'Organization+WebSite' ? ok('JSON-LD Organization + WebSite') : fail(`${label}: JSON-LD ${seo.ld.join() || 'chybí'}`);
				if (seo.image) {
					const img = await page.request.get(seo.image, { failOnStatusCode: false });
					img.status() === 200 ? ok('og:image se načte') : fail(`${label}: og:image vrací ${img.status()}`);
				}
			}

			errors.length ? fail(`${label}: chyby v konzoli: ${errors.join(' | ')}`) : ok('bez chyb v konzoli');

			await page.screenshot({ path: new URL(`./out/${vp.name}-${pg.name}.png`, import.meta.url).pathname, fullPage: true });
			} catch (e) {
				fail(`${label}: ${e.message.split('\n')[0]}`);
			} finally {
				await page.close();
			}
		}
		await context.close();
	}

	// Zabezpečení, které jde ověřit bez přihlášení.
	console.log('\nzabezpečení');
	const req = await browser.newContext();
	const xmlrpc = await req.request.post(`${BASE}/xmlrpc.php`, { data: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>', failOnStatusCode: false });
	xmlrpc.status() === 403 ? ok('XML-RPC vypnuté (403)') : fail(`XML-RPC vrací ${xmlrpc.status()}`);
	const users = await req.request.get(`${BASE}/wp-json/wp/v2/users`, { failOnStatusCode: false });
	users.status() === 404 ? ok('REST seznam uživatelů skrytý (404)') : fail(`REST /wp/v2/users vrací ${users.status()}`);
	const author = await req.request.get(`${BASE}/?author=1`, { failOnStatusCode: false, maxRedirects: 0 });
	const authorTo = (author.headers().location || '').replace(/\/$/, '');
	author.status() === 301 && authorTo === BASE ? ok('?author=1 přesměruje na úvod') : fail(`?author=1 vrací ${author.status()} ${authorTo}`);
	const sitemap = await req.request.get(`${BASE}/wp-sitemap.xml`, { failOnStatusCode: false });
	const sitemapBody = await sitemap.text();
	sitemapBody.includes('wp-sitemap-users') ? fail('sitemap obsahuje uživatele') : ok('sitemap bez uživatelů');
	const llms = await req.request.get(`${BASE}/llms.txt`, { failOnStatusCode: false });
	const llmsBody = await llms.text();
	llms.status() === 200 && llmsBody.startsWith('# ') && llmsBody.includes('## ')
		? ok('/llms.txt') : fail(`/llms.txt vrací ${llms.status()}`);
	await req.close();

	// Cookie lišta a GA4: bez souhlasu žádný požadavek na Google, rozhodnutí se pamatuje, jde změnit v patičce.
	console.log('\ncookie lišta');
	for (const vp of VIEWPORTS) {
		const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch });
		const google = [];
		await context.route(/googletagmanager\.com|google-analytics\.com/, (route) => {
			google.push(route.request().url());
			route.fulfill({ status: 200, contentType: 'text/javascript', body: '' });
		});
		const page = await context.newPage();
		const errors = [];
		page.on('pageerror', (e) => errors.push(e.message));
		try {
			await page.goto(`${BASE}/`, { waitUntil: 'load' });
			const bar = page.locator('#mnd-consent');
			if (!(await bar.count())) {
				ok(`${vp.name}: ID GA4 není vyplněné – lišta ani měření se nenačítají`);
				continue;
			}
			await bar.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
			(await bar.isVisible()) ? ok(`${vp.name}: lišta se zobrazí`) : fail(`${vp.name}: lišta se nezobrazila`);
			google.length ? fail(`${vp.name}: požadavek na Google před souhlasem`) : ok(`${vp.name}: bez souhlasu žádný požadavek na Google`);
			const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
			overflow <= 0 ? ok(`${vp.name}: s lištou bez přetékání`) : fail(`${vp.name}: s lištou stránka přetéká o ${overflow} px`);
			await page.screenshot({ path: new URL(`./out/${vp.name}-cookie-lista.png`, import.meta.url).pathname });

			await page.locator('[data-mnd-consent="denied"]').click();
			await page.reload({ waitUntil: 'load' });
			!(await bar.isVisible()) && !google.length ? ok(`${vp.name}: odmítnutí se pamatuje, Google nic`) : fail(`${vp.name}: po odmítnutí lišta ${await bar.isVisible() ? 'zůstala' : 'zmizela'}, Google ${google.length}×`);

			await page.locator('[data-mnd-consent-open]').click();
			(await bar.isVisible()) ? ok(`${vp.name}: „Nastavení cookies“ lištu znovu otevře`) : fail(`${vp.name}: odkaz v patičce lištu neotevřel`);
			await page.locator('[data-mnd-consent="granted"]').click();
			await page.waitForTimeout(300);
			const granted = await page.evaluate(() => (window.dataLayer || []).some((a) => a[0] === 'consent' && a[1] === 'update' && a[2]?.analytics_storage === 'granted'));
			google.some((u) => u.includes('gtag/js')) && granted ? ok(`${vp.name}: po souhlasu consent update a gtag.js`) : fail(`${vp.name}: po souhlasu gtag.js ${google.length}×, update ${granted}`);

			const before = google.length;
			await page.reload({ waitUntil: 'load' });
			await page.waitForTimeout(300);
			!(await bar.isVisible()) && google.length > before ? ok(`${vp.name}: souhlas se pamatuje, měří se hned`) : fail(`${vp.name}: po souhlasu a obnovení lišta ${await bar.isVisible() ? 'zůstala' : 'skrytá'}, Google ${google.length - before}×`);
			errors.length ? fail(`${vp.name}: chyby JS: ${errors.join(' | ')}`) : ok(`${vp.name}: bez chyb JS`);
		} catch (e) {
			fail(`cookie lišta ${vp.name}: ${e.message.split('\n')[0]}`);
		} finally {
			await context.close();
		}
	}
} finally {
	await browser.close();
}

console.log(failures.length ? `\n${failures.length} chyb.` : '\nVše v pořádku.');
process.exit(failures.length ? 1 : 0);
