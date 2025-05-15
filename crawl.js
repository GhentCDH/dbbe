const { chromium } = require('playwright');
const fs = require('fs').promises;

(async () => {
    const STORAGE_STATE_PATH = 'storageState.json';
    const username = 'editor@dbbe.ugent.be';
    const password = 'test';

    // Protected app URL to test authentication & start crawling from
    const protectedEditUrl = 'http://dbbe-app-1:8000/book_clusters/edit';

    // Direct Keycloak login URL with proper params for your realm/client
    const keycloakLoginUrl =
        'http://keycloak:8080/realms/dbbe/protocol/openid-connect/auth' +
        '?client_id=dbbe' +
        '&redirect_uri=http%3A%2F%2Fdbbe-app-1%3A8000%2Fauth%2Fconnect-check%2Fkeycloak' +
        '&response_type=code' +
        '&scope=openid%20profile%20email%20roles';

    const browser = await chromium.launch({ headless: true });
    let context;

    // Perform login on Keycloak directly, save storageState
    async function performKeycloakLogin(context) {
        const page = await context.newPage();
        console.log('Navigating to Keycloak login...');
        await page.goto(keycloakLoginUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });

        console.log('Filling in Keycloak login...');
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

    // Check if current context/session is authenticated by visiting protected URL
    async function isAuthenticated(context, url) {
        const page = await context.newPage();
        console.log(`Checking authentication by visiting: ${url}`);
        const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
        const currentUrl = page.url();
        const status = response.status();

        console.log(`Visited URL: ${currentUrl} (status: ${status})`);
        await page.close();

        // If redirected to keycloak or login page => not authenticated
        if (currentUrl.includes('keycloak') || currentUrl.includes('/login') || status !== 200) {
            return false;
        }
        return true;
    }

    // Create or reuse authenticated context
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

    context = await getAuthenticatedContext();

    // Crawler setup
    const visited = new Set();
    const patternsSeen = new Set();
    const errors = [];

    // Exclude login, keycloak, logout and other admin or profiler paths
    const excludePatterns = [
        /\/_profiler/, /\/_wdt/, /\/_error/, /\/_twig/, /\/admin(\/|$)/,
        /search_api/, /\/login/, /keycloak/i, /\/logout/
    ];

    function normalizePath(urlStr) {
        try {
            const url = new URL(urlStr);
            const parts = url.pathname.split('/').filter(Boolean);
            const normalized = parts.map(part => /^\d+$/.test(part) ? ':id' : part).join('/');
            return `${url.origin}/${normalized}`;
        } catch {
            return urlStr;
        }
    }

    const MAX_CONCURRENT = 5;
    let activeCrawls = 0;
    const crawlQueue = [];

    async function crawl(url) {
        if (visited.has(url)) return;
        if (excludePatterns.some(p => p.test(url))) return;

        const normalized = normalizePath(url);
        if (patternsSeen.has(normalized)) return;

        visited.add(url);
        patternsSeen.add(normalized);

        while (activeCrawls >= MAX_CONCURRENT) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        activeCrawls++;
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
            await page.waitForTimeout(1000);

            const toggles = await page.$$(
                'button[aria-haspopup="true"], [aria-expanded="false"], .dropdown-toggle, .menu-toggle, [data-toggle="dropdown"], .has-submenu'
            );
            for (const toggle of toggles) {
                try {
                    await toggle.click();
                    await page.waitForTimeout(300);
                } catch { /* ignore */ }
            }

            const links = await page.$$eval('a[href]', anchors =>
                anchors
                    .filter(a => a.offsetParent !== null)
                    .map(a => a.href)
                    .filter(href => href.startsWith(window.location.origin))
            );

            await page.close();

            for (const link of links) {
                crawlQueue.push(crawl(link));
            }

        } catch (e) {
            errors.push({ url, message: `Navigation error: ${e.message}` });
            await page.close();
        }

        activeCrawls--;
    }

    // Start crawling from authenticated protected page
    crawlQueue.push(crawl(protectedEditUrl));

    while (crawlQueue.length > 0) {
        const batch = crawlQueue.splice(0, MAX_CONCURRENT);
        await Promise.all(batch);
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
