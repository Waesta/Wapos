// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * WAPOS E2E Test Configuration
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests/E2E',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : 1,
  reporter: 'html',
  
  use: {
    baseURL: 'http://localhost/wapos',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: {
    command: 'php -S localhost:8000 -t c:/xampp/htdocs/wapos',
    url: 'http://localhost:8000',
    reuseExistingServer: !process.env.CI,
  },
});
