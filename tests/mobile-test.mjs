import { chromium } from 'playwright';
const B = 'http://127.0.0.1:8765';
const errors = [];
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const page = await (await browser.newContext({
  viewport: { width: 412, height: 915 }, locale: 'fa-IR', deviceScaleFactor: 2, isMobile: true, hasTouch: true,
})).newPage();
page.on('pageerror', e => errors.push('pageerror: ' + e.message));
page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });

await page.goto(`${B}/login.html`);
await page.fill('#login', 'admin'); await page.fill('#password', 'Admin@1234');
await page.tap('.submit-btn');
await page.waitForURL(/admin/, { timeout: 15000 });
await page.waitForSelector('.stat-grid', { timeout: 15000 });
await page.waitForTimeout(1500);

const vw = 412;
console.log('=== پیشخوان روی موبایل ===');
console.log('  کارت آماری:', await page.locator('.stat-card').count());
console.log('  پیام خطا روی صفحه؟', await page.locator('.toast.error').count() ? '✗ هست' : '✓ نیست');
console.log('  نمودار:', await page.locator('.chart-bar').count(), 'ستون');

const closed = await page.locator('.sidebar').boundingBox();
const cv = Math.max(0, Math.min(closed.x + closed.width, vw) - Math.max(closed.x, 0));
console.log('  سایدبار بسته — قابل مشاهده:', Math.round(cv), 'px', cv <= 1 ? '✓' : '✗');
console.log('  سرریز افقی:', await page.evaluate(() => document.documentElement.scrollWidth), '/', vw);
await page.screenshot({ path: 'mobile-final-dash.png', fullPage: true });

console.log('\n=== باز و بسته کردن منو ===');
await page.tap('#admin-nav-toggle');
await page.waitForTimeout(600);
const open = await page.locator('.sidebar').boundingBox();
const ov = Math.max(0, Math.min(open.x + open.width, vw) - Math.max(open.x, 0));
console.log('  باز — قابل مشاهده:', Math.round(ov), 'px', ov > 200 ? '✓' : '✗');
console.log('  به لبه راست چسبیده؟', Math.abs((open.x + open.width) - vw) < 2 ? '✓' : '✗');
console.log('  آیتم‌های منو خوانا؟', await page.locator('.nav-item .nav-label').first().isVisible() ? '✓' : '✗');
await page.screenshot({ path: 'mobile-final-open.png' });

// navigate from the menu
await page.tap('.nav-item:has-text("نوشته‌ها")');
await page.waitForTimeout(1200);
console.log('  رفتن به نوشته‌ها:', page.url().includes('/posts') ? '✓' : '✗');
console.log('  منو خودکار بسته شد؟', (await page.locator('.sidebar.mobile-open').count()) === 0 ? '✓' : '✗');
await page.screenshot({ path: 'mobile-final-posts.png', fullPage: true });

console.log('\n=== خطاها ===');
console.log(errors.length ? errors.map(e => '  ' + e).join('\n') : '  هیچ');
await browser.close();
process.exit(errors.length ? 1 : 0);
