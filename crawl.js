// const { chromium } = require('playwright');
// const fs = require('fs').promises;
//
// (async () => {
//     // --- CONFIG ---
//     const keycloakTokenUrl = 'http://keycloak:8080/realms/dbbe/protocol/openid-connect/token'; // <-- your Keycloak token URL
//     const clientId = 'dbbe';
//     const clientSecret = 'CTop7KIDgJuGJJ25JdjaA7vCQZGfQhi5';
//     const username = 'editor@dbbe.ugent.be';
//     const password = 'test';
//
//     const STORAGE_STATE_PATH = 'storageState.json';
//
//     async function getKeycloakToken() {
//         const params = new URLSearchParams();
//         params.append('grant_type', 'password');
//         params.append('client_id', clientId);
//         if (clientSecret) params.append('client_secret', clientSecret);
//         params.append('username', username);
//         params.append('password', password);
//         console.log('Request body:', params.toString());
//         console.log('Request URL:', keycloakTokenUrl);
//
//         const res = await fetch(keycloakTokenUrl, {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//             body: params.toString(),
//         });
//
//         if (!res.ok) {
//             throw new Error(`Failed to get token: ${res.status} ${res.statusText}`);
//         }
//
//         const data = await res.json();
//         return data.access_token;
//     }
//
//     const browser = await chromium.launch({ headless: true });
//
//     let context;
//     try {
//         // Try loading existing storage state (previous authenticated session)
//         await fs.access(STORAGE_STATE_PATH);
//         console.log('Loading existing storage state...');
//         context = await browser.newContext({ storageState: STORAGE_STATE_PATH });
//     } catch {
//         // If no storage state, do token login flow & save state
//         console.log('No storage state found, logging in and saving state...');
//         context = await browser.newContext();
//         const page = await context.newPage();
//
//         // Get access token once
//         console.log('Requesting Keycloak token...');
//         const token = await getKeycloakToken();
//         console.log('Token acquired');
//
//         // Inject token into localStorage to authenticate
//         await page.goto('http://dbbe-app-1:8000', { waitUntil: 'domcontentloaded' });
//         await page.evaluate(t => {
//             localStorage.setItem('access_token', t);
//         }, token);
//
//         // Wait a bit to let app register the token (adjust if needed)
//         await page.waitForTimeout(1000);
//
//         // Save storage state to file for reuse
//         await context.storageState({ path: STORAGE_STATE_PATH });
//         console.log('Storage state saved.');
//
//         await page.close();
//     }
//
//     const visited = new Set();
//     const patternsSeen = new Set();
//     const errors = [];
//
//     const excludePatterns = [
//         /\/_profiler/, /\/_wdt/, /\/_error/, /\/_twig/, /\/admin(\/|$)/, /search_api/
//     ];
//
//     function normalizePath(urlStr) {
//         try {
//             const url = new URL(urlStr);
//             const parts = url.pathname.split('/').filter(Boolean);
//             const normalized = parts.map(part => /^\d+$/.test(part) ? ':id' : part).join('/');
//             return `${url.origin}/${normalized}`;
//         } catch {
//             return urlStr;
//         }
//     }
//
//     const MAX_CONCURRENT = 5;
//     let activeCrawls = 0;
//     const crawlQueue = [];
//
//     async function crawl(url) {
//         if (visited.has(url)) return;
//         if (excludePatterns.some(p => p.test(url))) return;
//
//         const normalized = normalizePath(url);
//         if (patternsSeen.has(normalized)) return;
//
//         visited.add(url);
//         patternsSeen.add(normalized);
//
//         while (activeCrawls >= MAX_CONCURRENT) {
//             await new Promise(resolve => setTimeout(resolve, 100));
//         }
//
//         activeCrawls++;
//         const page = await context.newPage();
//
//         page.on('console', msg => {
//             if (msg.type() === 'error' && !msg.text().includes('fburl.com/debugjs')) {
//                 errors.push({ url: page.url(), message: msg.text() });
//             }
//         });
//
//         page.on('pageerror', err => errors.push({ url: page.url(), message: err.message }));
//
//         page.on('response', response => {
//             if (response.url().includes('creativecommons.org')) return;
//             if (!response.ok()) {
//                 errors.push({ url: response.url(), message: `HTTP ${response.status()}` });
//             }
//         });
//
//         try {
//             console.log(`Visiting ${url}`);
//             await page.goto(url, { waitUntil: 'networkidle', ignoreHTTPSErrors: true });
//             await page.waitForTimeout(1000);
//
//             const isLoggedIn = await page.evaluate(() => {
//                 return !!document.querySelector('a[href="/auth/logout"]');
//             });
//
//             const links = await page.$$eval('a[href]', anchors =>
//                 anchors.map(a => a.href).filter(href =>
//                     href.startsWith(window.location.origin)
//                 )
//             );
//
//             await page.close();
//
//             for (const link of links) {
//                 crawlQueue.push(crawl(link));
//             }
//
//         } catch (e) {
//             errors.push({ url, message: `Navigation error: ${e.message}` });
//             await page.close();
//         }
//
//         activeCrawls--;
//     }
//
//     crawlQueue.push(crawl('http://dbbe-app-1:8000'));
//
//     while (crawlQueue.length > 0) {
//         const batch = crawlQueue.splice(0, MAX_CONCURRENT);
//         await Promise.all(batch);
//     }
//
//     console.log('\n--- Errors ---');
//     if (errors.length) {
//         errors.forEach(e => console.log(`${e.url}: ${e.message}`));
//     } else {
//         console.log('No console errors found ✅');
//     }
//
//     await browser.close();
// })();
//
//
//

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


// WORKING AUTH
// const { chromium } = require('playwright');
// const fs = require('fs').promises;
//
// (async () => {
//     const STORAGE_STATE_PATH = 'storageState.json';
//     const username = 'editor@dbbe.ugent.be';
//     const password = 'test';
//     const protectedEditUrl = 'http://dbbe-app-1:8000/book_clusters/edit';
//
//     const keycloakLoginUrl = 'http://keycloak:8080/realms/dbbe/protocol/openid-connect/auth' +
//         '?client_id=dbbe' +
//         '&redirect_uri=http%3A%2F%2Fdbbe-app-1%3A8000%2Fauth%2Fconnect-check%2Fkeycloak' +
//         '&response_type=code' +
//         '&scope=openid%20profile%20email%20roles';
//
//     const browser = await chromium.launch({ headless: true });
//
//     async function performKeycloakLogin(context) {
//         const page = await context.newPage();
//         console.log('Navigating to Keycloak login...');
//         await page.goto(keycloakLoginUrl, { waitUntil: 'domcontentloaded' });
//
//         console.log('Filling in Keycloak login...');
//         await page.fill('#username', username);
//         await page.fill('#password', password);
//
//         await Promise.all([
//             page.click('#kc-login'),
//             page.waitForNavigation({ waitUntil: 'networkidle' }),
//         ]);
//
//         console.log('Keycloak login done.');
//         await context.storageState({ path: STORAGE_STATE_PATH });
//         await page.close();
//     }
//
//     async function createFreshContextAndLogin() {
//         const context = await browser.newContext();
//         await performKeycloakLogin(context);
//         return context;
//     }
//
//     // Try loading saved storage state
//     let context;
//     try {
//         await fs.access(STORAGE_STATE_PATH);
//         console.log('Loading existing storage state...');
//         context = await browser.newContext({ storageState: STORAGE_STATE_PATH });
//
//         const page = await context.newPage();
//         const response = await page.goto(protectedEditUrl, { waitUntil: 'networkidle', timeout: 30000 });
//
//         const finalUrl = page.url();
//         console.log(`Visited protected URL, final URL: ${finalUrl}`);
//         console.log(`Response status: ${response.status()}`);
//
//         if (finalUrl.includes('keycloak') || finalUrl.includes('/login')) {
//             console.log('Not authenticated. Clearing storage and retrying login...');
//             await page.close();
//             await fs.unlink(STORAGE_STATE_PATH);
//             context = await createFreshContextAndLogin();
//         } else {
//             console.log('Authenticated access confirmed ✅');
//             await page.close();
//         }
//     } catch (err) {
//         console.log('No valid storage or error occurred, logging in...');
//         context = await createFreshContextAndLogin();
//     }
//
//     // Test: visit again with valid session
//     const testPage = await context.newPage();
//     const final = await testPage.goto(protectedEditUrl, { waitUntil: 'networkidle' });
//     console.log(`Final re-check URL: ${testPage.url()}`);
//     console.log(`Final response status: ${final.status()}`);
//
//     await testPage.close();
//     await browser.close();
// })();
