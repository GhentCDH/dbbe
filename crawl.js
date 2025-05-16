const { chromium } = require('playwright');
const fs = require('fs').promises;

(async () => {
    const STORAGE_STATE_PATH = 'storageState.json';
    const username = 'editor@dbbe.ugent.be';
    const password = 'test';

    const protectedEditUrl = 'http://dbbe-app-1:8000/book_clusters/edit';
    const keycloakLoginUrl =
        'http://keycloak:8080/realms/dbbe/protocol/openid-connect/auth' +
        '?client_id=dbbe' +
        '&redirect_uri=http%3A%2F%2Fdbbe-app-1%3A8000%2Fauth%2Fconnect-check%2Fkeycloak' +
        '&response_type=code' +
        '&scope=openid%20profile%20email%20roles';

    const browser = await chromium.launch({ headless: process.env.HEADLESS !== 'false' });

    async function performKeycloakLogin(context) {
        const page = await context.newPage();
        console.log('Navigating to Keycloak login...');
        await page.goto(keycloakLoginUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });

        await page.fill('#username', username);
        await page.fill('#password', password);

        await Promise.all([
            page.click('#kc-login'),
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
        ]);

        console.log('Keycloak login done.');
        await context.storageState({ path: STORAGE_STATE_PATH });
        await page.close();
    }

    async function isAuthenticated(context, url) {
        const page = await context.newPage();
        const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
        const currentUrl = page.url();
        await page.close();

        return !(currentUrl.includes('keycloak') || currentUrl.includes('/login') || response.status() !== 200);
    }

    async function getAuthenticatedContext() {
        try {
            await fs.access(STORAGE_STATE_PATH);
            console.log('Loading existing storage state...');
            const ctx = await browser.newContext({ storageState: STORAGE_STATE_PATH });

            if (!(await isAuthenticated(ctx, protectedEditUrl))) {
                console.log('Existing storage state invalid, removing and re-logging in...');
                await fs.unlink(STORAGE_STATE_PATH);
                await ctx.close();
                const freshCtx = await browser.newContext();
                await performKeycloakLogin(freshCtx);
                return freshCtx;
            }

            console.log('Using existing authenticated context ✅');
            return ctx;
        } catch {
            console.log('No storage state found, performing login...');
            const ctx = await browser.newContext();
            await performKeycloakLogin(ctx);
            return ctx;
        }
    }

    const context = await getAuthenticatedContext();

    const visited = new Set();
    const normalizedSeen = new Set();
    const errors = [];

    const excludePatterns = [
        /\/_profiler/, /\/_wdt/, /\/_error/, /\/_twig/, /\/admin(\/|$)/,
        /search_api/, /\/login/, /keycloak/i, /\/logout/
    ];

    function normalizePath(urlStr) {
        try {
            const url = new URL(urlStr);
            url.hash = '';
            url.searchParams.sort();
            const normalizedPath = url.pathname.replace(/\/\d+/g, '/:id').replace(/\/+$/, '');
            return `${url.origin}${normalizedPath}${url.search}`;
        } catch {
            return urlStr;
        }
    }

    const MAX_CONCURRENT = 10;
    const crawlQueue = [];

    async function crawl(url) {
        if (visited.has(url) || excludePatterns.some(p => p.test(url))) return;

        const normalized = normalizePath(url);
        if (normalizedSeen.has(normalized)) return;

        visited.add(url);
        normalizedSeen.add(normalized);

        const page = await context.newPage();

        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('fburl.com/debugjs')) {
                errors.push({ url: page.url(), message: msg.text() });
            }
        });

        page.on('pageerror', err => errors.push({ url: page.url(), message: err.message }));

        page.on('response', response => {
            if (!response.ok() && !response.url().includes('creativecommons.org')) {
                errors.push({ url: response.url(), message: `HTTP ${response.status()}` });
            }
        });

        try {
            console.log(`Visiting ${url}`);
            await page.goto(url, { waitUntil: 'networkidle', ignoreHTTPSErrors: true, timeout: 30000 });

            await page.waitForTimeout(500);

            await page.$$eval(
                'button[aria-haspopup="true"], [aria-expanded="false"], .dropdown-toggle, .menu-toggle, [data-toggle="dropdown"], .has-submenu',
                toggles => toggles.forEach(t => t.click())
            );

            const links = await page.$$eval('a[href]', anchors =>
                anchors
                    .map(a => a.href)
                    .filter(href =>
                        href.startsWith(window.location.origin) &&
                        !href.includes('#') &&
                        !href.startsWith('javascript:') &&
                        href.trim() !== ''
                    )
            );

            for (const link of links) {
                crawlQueue.push(crawl(link));
            }

        } catch (e) {
            errors.push({ url, message: `Navigation error: ${e.message}` });
        } finally {
            await page.close();
        }
    }

    // Kick off crawl
    crawlQueue.push(crawl(protectedEditUrl));

    while (crawlQueue.length > 0) {
        const batch = crawlQueue.splice(0, MAX_CONCURRENT);
        await Promise.allSettled(batch); // Don’t block on failures
    }

    console.log('\n--- Errors ---');
    if (errors.length) {
        errors.forEach(e => console.log(`${e.url}: ${e.message}`));
    } else {
        console.log('No console errors found ✅');
    }

    await context.close();
    await browser.close();
})();
