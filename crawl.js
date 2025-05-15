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

// const { chromium } = require('playwright');
// const fs = require('fs').promises;
//
// (async () => {
//     // --- CONFIG ---
//     const STORAGE_STATE_PATH = 'storageState.json';
//
//     const loginUrl = 'http://dbbe-app-1:8000/login'; // Your real login page URL
//     const username = 'editor@dbbe.ugent.be';
//     const password = 'test';
//
//     const browser = await chromium.launch({ headless: true });
//     let context;
//
//     async function performLogin(page) {
//         console.log('Navigating to login page...');
//         await page.goto(loginUrl, { waitUntil: 'networkidle' });
//
//         console.log('Filling login form...');
//         await page.fill('input[name="username"]', username);
//         await page.fill('input[name="password"]', password);
//
//         console.log('Submitting login form...');
//         await Promise.all([
//             page.click('button[type="submit"]'),
//             page.waitForNavigation({ waitUntil: 'networkidle' }),
//         ]);
//
//         console.log('Login completed.');
//     }
//
//     try {
//         await fs.access(STORAGE_STATE_PATH);
//         console.log('Loading existing storage state...');
//         context = await browser.newContext({ storageState: STORAGE_STATE_PATH });
//     } catch {
//         console.log('No storage state found, performing login...');
//         context = await browser.newContext();
//         const page = await context.newPage();
//
//         await performLogin(page);
//
//         console.log('Saving storage state...');
//         await context.storageState({ path: STORAGE_STATE_PATH });
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
//             // CLICK ALL TOGGLES TO REVEAL HIDDEN MENUS
//             const toggles = await page.$$(
//                 'button[aria-haspopup="true"], [aria-expanded="false"], .dropdown-toggle, .menu-toggle, [data-toggle="dropdown"], .has-submenu'
//             );
//
//             for (const toggle of toggles) {
//                 try {
//                     await toggle.click();
//                     await page.waitForTimeout(300);
//                 } catch {
//                     // ignore toggle click errors
//                 }
//             }
//
//             // EXTRACT VISIBLE LINKS AFTER TOGGLING MENUS
//             const links = await page.$$eval('a[href]', anchors =>
//                 anchors
//                     .filter(a => a.offsetParent !== null) // only visible links
//                     .map(a => a.href)
//                     .filter(href => href.startsWith(window.location.origin))
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
//     crawlQueue.push(crawl('http://localhost:8001/book_clusters/edit'))
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


const { chromium } = require('playwright');
const fs = require('fs').promises;

(async () => {
    const STORAGE_STATE_PATH = 'storageState.json';
    const loginUrl = 'http://dbbe-app-1:8000/login';  // Your login page URL
    const username = 'editor@dbbe.ugent.be';
    const password = 'test';
    const protectedEditUrl = 'http://dbbe-app-1:8000/book_clusters/edit';

    const browser = await chromium.launch({ headless: true });
    let context;

    try {
        await fs.access(STORAGE_STATE_PATH);
        console.log('Loading existing storage state...');
        context = await browser.newContext({ storageState: STORAGE_STATE_PATH });
    } catch {
        console.log('No storage state found, creating new context...');
        context = await browser.newContext();
    }

    const page = await context.newPage();

    // If no storage state, perform login form submit
    if (!context.storageState) {
        console.log('Performing login...');

        await page.goto(loginUrl, { waitUntil: 'networkidle' });

        await page.fill('input[name="username"]', username);
        await page.fill('input[name="password"]', password);

        await Promise.all([
            page.click('button[type="submit"]'),
            page.waitForNavigation({ waitUntil: 'networkidle' }),
        ]);

        console.log('Login done, saving storage state...');
        await context.storageState({ path: STORAGE_STATE_PATH });
    }

    // Now visit protected page
    console.log(`Visiting protected URL: ${protectedEditUrl}`);
    const response = await page.goto(protectedEditUrl, { waitUntil: 'networkidle', timeout: 30000 });

    console.log(`Final URL after navigation: ${page.url()}`);
    console.log(`Response status: ${response.status()}`);

    if (page.url().includes('/login') || page.url().includes('keycloak')) {
        console.log('Redirected to login/keycloak - not authenticated.');
    } else {
        console.log('Authenticated access confirmed.');
    }

    await page.close();
    await browser.close();
})();
