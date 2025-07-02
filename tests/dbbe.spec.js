// tests/site-crawler.spec.js
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Configuration - Update these values
const CONFIG = {
    baseUrl: 'http://dbbe-app-1:8000', // Replace with your site URL
    credentials: {
        username: 'editor@dbbe.ugent.be', // Replace with your username
        password: 'test'  // Replace with your password
    },
    maxPages: 500,
    delay: 1000,
    linksToClickOnSearch: 3 // Number of links to click on search pages
};

class SiteCrawler {
    constructor() {
        this.visited = new Set();
        this.toVisit = new Set();
        this.results = [];
        this.errors = [];

        this.excludePatterns = [
            /\/_profiler/,
            /\/_wdt/,
            /\/_error/,
            /\/_twig/,
            /\/admin(\/|$)/,
            /search_api/,
            /\/login/,
            /keycloak/i,
            /\/logout/,
            /\/search-tips-tricks/,
            /\/pages\/help/
        ];

        this.searchEndpoints = [
            '/occurrences/search',
            '/persons/search',
            '/types/search',
            '/manuscripts/search',
            '/bibliography/search'
        ];

        this.sampledEntities = new Set();
        this.linksClickedOnSearch = 0; // Track links clicked on search pages
    }

    shouldExclude(url) {
        const urlPath = new URL(url, CONFIG.baseUrl).pathname;
        return this.excludePatterns.some(pattern => pattern.test(urlPath));
    }

    isSearchEndpoint(url) {
        const urlPath = new URL(url, CONFIG.baseUrl).pathname;
        return this.searchEndpoints.some(endpoint => urlPath.includes(endpoint));
    }

    isSearchPage(url) {
        const urlPath = new URL(url, CONFIG.baseUrl).pathname;
        return urlPath.includes('/search');
    }

    shouldSampleEntity(url) {
        const urlPath = new URL(url, CONFIG.baseUrl).pathname;

        const entityPatterns = [
            /\/occurrences\/\d+$/,
            /\/persons\/\d+$/,
            /\/types\/\d+$/,
            /\/manuscripts\/\d+$/,
            /\/bibliography\/\d+$/
        ];

        for (const pattern of entityPatterns) {
            if (pattern.test(urlPath)) {
                const entityType = urlPath.split('/')[1];
                if (!this.sampledEntities.has(entityType)) {
                    this.sampledEntities.add(entityType);
                    return true;
                }
                return false;
            }
        }

        return true;
    }

    async login(page) {
        console.log('🔐 Attempting to login...');

        try {
            await page.goto(CONFIG.baseUrl + '/login');
            await page.waitForSelector('#kc-form-login', { timeout: 5000 });

            // Fill username & password
            await page.fill('#username', CONFIG.credentials.username);
            await page.fill('#password', CONFIG.credentials.password);

            // Submit the form by clicking the input[type=submit]
            await Promise.all([
                // page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
                page.click('input#kc-login[type="submit"]'),
            ]);
            return true;

        } catch (error) {
            console.error('❌ Login failed:', error.message);
            this.errors.push({
                url: 'LOGIN',
                error: error.message,
                timestamp: new Date().toISOString()
            });
            return false;
        }
    }

    async extractLinks(page) {
        return await page.evaluate(() => {
            const links = [];
            const elements = document.querySelectorAll('a[href]');

            elements.forEach(el => {
                const href = el.getAttribute('href');
                if (href && !href.startsWith('mailto:') && !href.startsWith('tel:')) {
                    links.push(href);
                }
            });

            return links;
        });
    }

    async clickLinksOnSearchPage(page, url) {
        if (!this.isSearchPage(url)) {
            return 0;
        }

        console.log(`🔗 Clicking links on search page: ${url}`);
        let linksClicked = 0;

        try {
            // Find clickable links (excluding buttons and form inputs)
            const linkElements = await page.locator('a[href]:not([href^="mailto:"]):not([href^="tel:"])').all();

            // Filter out links that would be excluded or are navigation/UI elements
            const clickableLinks = [];
            for (const link of linkElements) {
                try {
                    const href = await link.getAttribute('href');
                    if (href) {
                        const absoluteUrl = new URL(href, url).href;
                        // Only click links that lead to pages we would crawl
                        if (absoluteUrl.startsWith(CONFIG.baseUrl) &&
                            !this.shouldExclude(absoluteUrl) &&
                            await link.isVisible() &&
                            await link.isEnabled()) {
                            clickableLinks.push(link);
                        }
                    }
                } catch (e) {
                    // Skip this link if there's an error
                    continue;
                }
            }

            // Click up to the configured number of links
            const linksToClick = Math.min(clickableLinks.length, CONFIG.linksToClickOnSearch);

            for (let i = 0; i < linksToClick; i++) {
                try {
                    console.log(`  🖱️ Clicking link ${i + 1}/${linksToClick}`);

                    // Get the href before clicking in case navigation happens
                    const href = await clickableLinks[i].getAttribute('href');

                    await clickableLinks[i].click({ timeout: 5000 });
                    await page.waitForLoadState('networkidle', { timeout: 10000 });

                    linksClicked++;
                    this.linksClickedOnSearch++;

                    // Small delay between clicks
                    await page.waitForTimeout(500);

                    // If we navigated away, go back to the search page
                    if (page.url() !== url) {
                        console.log(`  🔙 Navigating back to search page`);
                        await page.goBack();
                        await page.waitForLoadState('networkidle');
                    }

                } catch (clickError) {
                    console.warn(`⚠️ Failed to click link ${i + 1} on ${url}:`, clickError.message);
                    // Try to get back to the search page if we're lost
                    if (page.url() !== url) {
                        try {
                            await page.goto(url);
                            await page.waitForLoadState('networkidle');
                        } catch (navError) {
                            console.warn(`⚠️ Failed to navigate back to search page: ${navError.message}`);
                            break;
                        }
                    }
                }
            }

            console.log(`  ✅ Clicked ${linksClicked} links on search page`);

        } catch (error) {
            console.error(`❌ Error clicking links on search page ${url}:`, error.message);
        }

        return linksClicked;
    }

    async crawlPage(page, url) {
        if (this.visited.has(url) || this.shouldExclude(url) || !this.shouldSampleEntity(url)) {
            return { skipped: true };
        }

        console.log(`🔍 Crawling: ${url}`);
        this.visited.add(url);

        const startTime = Date.now();
        let status = 'success';
        let errorMessage = null;
        let clickableElements = 0;
        let linksFound = 0;
        let searchLinksClicked = 0;

        try {
            const response = await page.goto(url, {
                waitUntil: 'networkidle',
                timeout: 30000
            });

            if (!response || !response.ok()) {
                throw new Error(`HTTP ${response?.status() || 'unknown'}`);
            }

            // Find and click all clickable elements (buttons, form inputs)
            const clickables = await page.locator('button, input[type="button"], input[type="submit"], [role="button"], .btn').all();
            clickableElements = clickables.length;

            for (const element of clickables) {
                try {
                    if (await element.isVisible() && await element.isEnabled()) {
                        await element.click({ timeout: 5000 });
                        await page.waitForTimeout(500);
                    }
                } catch (clickError) {
                    console.warn(`⚠️ Click failed on ${url}:`, clickError.message);
                }
            }

            // Click links on search pages
            if (this.isSearchPage(url)) {
                searchLinksClicked = await this.clickLinksOnSearchPage(page, url);
            }

            // Extract links
            const links = await this.extractLinks(page);
            linksFound = links.length;

            // Add new links to visit queue
            if (!this.isSearchEndpoint(url)) {
                links.forEach(link => {
                    try {
                        const absoluteUrl = new URL(link, url).href;
                        if (absoluteUrl.startsWith(CONFIG.baseUrl) &&
                            !this.visited.has(absoluteUrl) &&
                            !this.shouldExclude(absoluteUrl) &&
                            this.shouldSampleEntity(absoluteUrl)) {
                            this.toVisit.add(absoluteUrl);
                        }
                    } catch (e) {
                        // Invalid URL, skip
                    }
                });
            }

        } catch (error) {
            status = 'error';
            errorMessage = error.message;
            console.error(`❌ Error crawling ${url}:`, error.message);

            this.errors.push({
                url,
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }

        const result = {
            url,
            status,
            duration: Date.now() - startTime,
            clickableElements,
            linksFound,
            searchLinksClicked,
            isSearchPage: this.isSearchPage(url),
            error: errorMessage,
            timestamp: new Date().toISOString()
        };

        this.results.push(result);
        await page.waitForTimeout(CONFIG.delay);

        return result;
    }

    async generateReport() {
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const reportDir = path.join(process.cwd(), 'test-results', 'crawler-reports');

        if (!fs.existsSync(reportDir)) {
            fs.mkdirSync(reportDir, { recursive: true });
        }

        const totalPages = this.results.length;
        const successfulPages = this.results.filter(r => r.status === 'success').length;
        const errorPages = this.results.filter(r => r.status === 'error').length;
        const totalDuration = this.results.reduce((sum, r) => sum + r.duration, 0);
        const avgDuration = totalPages > 0 ? Math.round(totalDuration / totalPages) : 0;
        const searchPagesVisited = this.results.filter(r => r.isSearchPage).length;

        const report = {
            summary: {
                timestamp: new Date().toISOString(),
                totalPages,
                successfulPages,
                errorPages,
                successRate: `${Math.round((successfulPages / totalPages) * 100)}%`,
                totalDuration: `${Math.round(totalDuration / 1000)}s`,
                avgDuration: `${avgDuration}ms`,
                sampledEntities: Array.from(this.sampledEntities),
                searchPagesVisited,
                totalLinksClickedOnSearch: this.linksClickedOnSearch,
                linksToClickPerSearch: CONFIG.linksToClickOnSearch
            },
            pages: this.results,
            errors: this.errors
        };

        // Save JSON report
        const jsonFile = path.join(reportDir, `crawler-report-${timestamp}.json`);
        fs.writeFileSync(jsonFile, JSON.stringify(report, null, 2));

        // Generate HTML report
        const htmlReport = this.generateHtmlReport(report);
        const htmlFile = path.join(reportDir, `crawler-report-${timestamp}.html`);
        fs.writeFileSync(htmlFile, htmlReport);

        console.log(`📊 Report saved to: ${htmlFile}`);
        return report;
    }

    generateHtmlReport(report) {
        return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Crawler Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .summary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                  color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary h2 { margin-top: 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .stat-card { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 14px; opacity: 0.9; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .search-page { background: rgba(255, 193, 7, 0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-success { background: rgba(40, 167, 69, 0.1); }
        .status-error { background: rgba(220, 53, 69, 0.1); }
        .url-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-search { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕷️ Site Crawler Report</h1>
        
        <div class="summary">
            <h2>Summary</h2>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value">${report.summary.totalPages}</div>
                    <div class="stat-label">Total Pages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value success">${report.summary.successfulPages}</div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value error">${report.summary.errorPages}</div>
                    <div class="stat-label">Errors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${report.summary.successRate}</div>
                    <div class="stat-label">Success Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${report.summary.searchPagesVisited}</div>
                    <div class="stat-label">Search Pages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${report.summary.totalLinksClickedOnSearch}</div>
                    <div class="stat-label">Links Clicked</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${report.summary.totalDuration}</div>
                    <div class="stat-label">Total Duration</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${report.summary.avgDuration}</div>
                    <div class="stat-label">Avg Page Load</div>
                </div>
            </div>
            <p style="margin-top: 20px;"><strong>Sampled Entities:</strong> ${report.summary.sampledEntities.join(', ')}</p>
            <p><strong>Links per Search Page:</strong> ${report.summary.linksToClickPerSearch}</p>
            <p><strong>Generated:</strong> ${report.summary.timestamp}</p>
        </div>

        <h2>📄 Page Results</h2>
        <table>
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Clickables</th>
                    <th>Links</th>
                    <th>Search Links Clicked</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                ${report.pages.map(page => `
                    <tr class="status-${page.status} ${page.isSearchPage ? 'search-page' : ''}">
                        <td class="url-cell">
                            <a href="${page.url}" target="_blank" title="${page.url}">${page.url}</a>
                            ${page.isSearchPage ? '<span class="badge badge-search">SEARCH</span>' : ''}
                        </td>
                        <td><span class="badge badge-${page.status}">${page.status.toUpperCase()}</span></td>
                        <td>${page.duration}ms</td>
                        <td>${page.clickableElements}</td>
                        <td>${page.linksFound}</td>
                        <td>${page.searchLinksClicked || 0}</td>
                        <td>${page.error || ''}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
</body>
</html>`;
    }
}

// Global crawler instance
let crawler;

test.describe('Site Crawler', () => {
    test.beforeAll(async () => {
        crawler = new SiteCrawler();
    });

    test('should crawl entire site and generate report', async ({ page }) => {
        console.log('🚀 Starting comprehensive site crawl...');
        console.log(`🔗 Will click ${CONFIG.linksToClickOnSearch} links on each search page`);

        // Login first
        const loginSuccess = await crawler.login(page);
        expect(loginSuccess).toBe(true);

        // Start crawling from base URL
        crawler.toVisit.add(CONFIG.baseUrl);

        let pagesProcessed = 0;
        const maxPages = CONFIG.maxPages;

        // Crawl pages iteratively
        while (crawler.toVisit.size > 0 && pagesProcessed < maxPages) {
            const url = crawler.toVisit.values().next().value;
            crawler.toVisit.delete(url);

            const result = await crawler.crawlPage(page, url);

            if (!result.skipped) {
                pagesProcessed++;

                // Soft assertion - log errors but don't fail the test
                if (result.status === 'error') {
                    console.error(`❌ Page failed: ${result.url} - ${result.error}`);
                } else {
                    const searchInfo = result.isSearchPage ? ` (search page, clicked ${result.searchLinksClicked} links)` : '';
                    console.log(`✅ Page success: ${result.url} (${result.duration}ms)${searchInfo}`);
                }
            }
        }

        console.log(`\n📊 Crawl completed! Processed ${pagesProcessed} pages`);
        console.log(`🔗 Total links clicked on search pages: ${crawler.linksClickedOnSearch}`);

        // Generate final report
        const report = await crawler.generateReport();

        // Log summary
        console.log(`\n📈 Final Results:`);
        console.log(`✅ Successful pages: ${report.summary.successfulPages}`);
        console.log(`❌ Failed pages: ${report.summary.errorPages}`);
        console.log(`🔍 Search pages visited: ${report.summary.searchPagesVisited}`);
        console.log(`🔗 Links clicked on search pages: ${report.summary.totalLinksClickedOnSearch}`);
        console.log(`🎯 Success rate: ${report.summary.successRate}`);
        console.log(`🏷️ Sampled entities: ${report.summary.sampledEntities.join(', ')}`);

        // The test passes if we successfully generated a report
        // Individual page failures are logged but don't fail the entire test
        expect(report.summary.totalPages).toBeGreaterThan(0);
        expect(report.summary.successRate).toBeDefined();
    });
});

// Export for potential reuse
module.exports = { SiteCrawler, CONFIG };