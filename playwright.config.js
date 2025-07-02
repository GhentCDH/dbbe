// playwright.config.js
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests',

    timeout: 30 * 60 * 1000,

    expect: {
        timeout: 10000
    },

    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['html', { outputFolder: 'test-results/playwright-report' }],
        ['json', { outputFile: 'test-results/results.json' }],
        ['list']
    ],
    use: {
        baseURL: 'http://dbbe-app-1:8001',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        navigationTimeout: 30000,
        actionTimeout: 10000
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                headless: true,
                slowMo: 500
            },
        }
    ],

});