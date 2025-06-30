// playwright.config.js
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests',

    // Global test timeout (30 minutes for full crawl)
    timeout: 30 * 60 * 1000,

    // Expect timeout for individual assertions
    expect: {
        timeout: 10000
    },

    // Run tests in serial (one at a time) to avoid overwhelming the server
    fullyParallel: false,
    workers: 1,

    // Fail the build on CI if you accidentally left test.only in source code
    forbidOnly: !!process.env.CI,

    // Retry on CI only
    retries: process.env.CI ? 1 : 0,

    // Reporter configuration
    reporter: [
        ['html', { outputFolder: 'test-results/playwright-report' }],
        ['json', { outputFile: 'test-results/results.json' }],
        ['list']
    ],

    // Shared settings for all projects
    use: {
        // Base URL for your site
        baseURL: 'http://dbbe-app-1:8001', // Update this

        // Collect trace when retrying the failed test
        trace: 'on-first-retry',

        // Take screenshot on failure
        screenshot: 'only-on-failure',

        // Record video on failure
        video: 'retain-on-failure',

        // Navigation timeout
        navigationTimeout: 30000,

        // Action timeout
        actionTimeout: 10000
    },

    // Configure projects for major browsers
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // Run in headed mode to see the crawling in action
                headless: true,
                // Slow down actions to be more human-like
                slowMo: 500
            },
        }

        // Uncomment these if you want to test on other browsers
        // {
        //   name: 'firefox',
        //   use: { ...devices['Desktop Firefox'] },
        // },
        // {
        //   name: 'webkit',
        //   use: { ...devices['Desktop Safari'] },
        // },
    ],

    // Run your local dev server before starting the tests
    // webServer: {
    //   command: 'npm run start',
    //   port: 3000,
    // },
});