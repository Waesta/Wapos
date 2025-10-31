// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Cashier Sale Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Login as cashier
    await page.goto('/login.php');
    await page.fill('input[name="username"]', 'cashier');
    await page.fill('input[name="password"]', 'cashier123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard/);
  });

  test('should create a retail sale with discount and tax', async ({ page }) => {
    // Navigate to POS
    await page.goto('/pos.php');
    await expect(page.locator('h1')).toContainText('Point of Sale');

    // Add product by barcode
    await page.fill('input[name="barcode"]', '123456789');
    await page.press('input[name="barcode"]', 'Enter');

    // Wait for product to be added
    await expect(page.locator('.cart-items')).toContainText('Product');

    // Set quantity
    await page.fill('input[name="quantity"]', '2');

    // Apply discount
    await page.click('button:has-text("Discount")');
    await page.fill('input[name="discount"]', '5');
    await page.click('button:has-text("Apply")');

    // Verify totals
    const subtotal = await page.locator('.subtotal').textContent();
    const tax = await page.locator('.tax').textContent();
    const total = await page.locator('.total').textContent();

    expect(parseFloat(subtotal)).toBeGreaterThan(0);
    expect(parseFloat(tax)).toBeGreaterThan(0);
    expect(parseFloat(total)).toBeGreaterThan(0);

    // Complete sale
    await page.click('button:has-text("Complete Sale")');
    await page.fill('input[name="amount_paid"]', '100');
    await page.click('button:has-text("Confirm")');

    // Verify receipt
    await expect(page.locator('.receipt')).toBeVisible();
    await expect(page.locator('.receipt')).toContainText('Thank you');

    // Print receipt
    await page.click('button:has-text("Print Receipt")');
    
    // Verify sale was recorded
    await page.goto('/sales.php');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
  });

  test('should handle offline sale and sync when online', async ({ page, context }) => {
    // Go offline
    await context.setOffline(true);

    // Navigate to POS
    await page.goto('/pos.php');

    // Create sale
    await page.fill('input[name="barcode"]', '123456789');
    await page.press('input[name="barcode"]', 'Enter');
    await page.click('button:has-text("Complete Sale")');
    await page.fill('input[name="amount_paid"]', '50');
    await page.click('button:has-text("Confirm")');

    // Verify queued message
    await expect(page.locator('.alert')).toContainText('queued');

    // Go back online
    await context.setOffline(false);

    // Wait for sync
    await page.waitForTimeout(3000);

    // Verify sale synced
    await page.goto('/sales.php');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
  });

  test('should prevent duplicate sales with same external_id', async ({ page }) => {
    const externalId = 'test-' + Date.now();

    // Create first sale
    await page.goto('/pos.php');
    await page.evaluate((id) => {
      window.localStorage.setItem('pending_sale_external_id', id);
    }, externalId);

    await page.fill('input[name="barcode"]', '123456789');
    await page.press('input[name="barcode"]', 'Enter');
    await page.click('button:has-text("Complete Sale")');
    await page.fill('input[name="amount_paid"]', '50');
    await page.click('button:has-text("Confirm")');

    // Try to create duplicate
    await page.goto('/pos.php');
    await page.evaluate((id) => {
      window.localStorage.setItem('pending_sale_external_id', id);
    }, externalId);

    await page.fill('input[name="barcode"]', '123456789');
    await page.press('input[name="barcode"]', 'Enter');
    await page.click('button:has-text("Complete Sale")');
    await page.fill('input[name="amount_paid"]', '50');
    await page.click('button:has-text("Confirm")');

    // Should show duplicate message
    await expect(page.locator('.alert')).toContainText('already exists');
  });
});
