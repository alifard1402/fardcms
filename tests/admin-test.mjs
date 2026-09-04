import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8765';
const errors = [];
const results = [];

function chk(name, ok, detail = '') {
  results.push({ name, ok, detail });
  console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${!ok && detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 }, locale: 'fa-IR' });
const page = await ctx.newPage();

page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
page.on('pageerror', e => errors.push('pageerror: ' + e.message));
page.on('requestfailed', r => errors.push('reqfail: ' + r.url() + ' ' + r.failure()?.errorText));

// ─── login page ───
console.log('=== صفحه ورود ===');
await page.goto(`${BASE}/login.html`, { waitUntil: 'networkidle' });
chk('login page renders card', await page.locator('.auth-card').isVisible());
chk('login title present', (await page.locator('.auth-title').textContent()).includes('ورود'));
chk('font loaded (Vazirmatn)', await page.evaluate(() => document.fonts.check('16px Vazirmatn')));
chk('Vue mounted (v-if worked)', !(await page.content()).includes('v-if'));
await page.screenshot({ path: 'shot-login.png' });

// validation
await page.click('.submit-btn');
await page.waitForTimeout(300);
chk('client validation shows error', await page.locator('.field-error').first().isVisible());

// bad credentials
await page.fill('#login', 'admin');
await page.fill('#password', 'wrongpass');
await page.click('.submit-btn');
await page.waitForSelector('.message-error', { timeout: 5000 });
chk('server rejects bad password', (await page.locator('.message-error').textContent()).includes('اشتباه'));

// real login
await page.fill('#password', 'Admin@1234');
await page.click('.submit-btn');
await page.waitForURL(/admin\//, { timeout: 10000 });
chk('login redirects to admin', page.url().includes('/admin/'));

// ─── admin dashboard ───
console.log('=== پیشخوان ===');
await page.waitForSelector('.stat-grid', { timeout: 10000 });
chk('dashboard stat cards render', (await page.locator('.stat-card').count()) > 3);
chk('sidebar renders', await page.locator('.sidebar').isVisible());
chk('nav items present', (await page.locator('.nav-item').count()) > 6);
chk('chart bars render 30 days', (await page.locator('.chart-bar').count()) === 30);
chk('user name in topbar', (await page.locator('.user-name-label').textContent()).length > 0);
await page.screenshot({ path: 'shot-dashboard.png', fullPage: true });

// ─── theme toggle ───
await page.click('.topbar .icon-btn:nth-of-type(1)'); // may be sidebar; use explicit
await page.waitForTimeout(200);
const themeBtn = page.locator('.topbar .icon-btn').nth(1);
await themeBtn.click();
await page.waitForTimeout(400);
const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
chk('theme toggle switches to light', theme === 'light', 'got ' + theme);
await page.screenshot({ path: 'shot-light.png' });
await themeBtn.click();
await page.waitForTimeout(300);

// ─── navigate to posts ───
console.log('=== نوشته‌ها ===');
await page.click('.nav-item:has-text("نوشته‌ها")');
await page.waitForSelector('.table', { timeout: 8000 });
chk('posts table renders', (await page.locator('.table tbody tr').count()) >= 2);
chk('status filter tabs render', (await page.locator('.status-filter').count()) >= 2);
await page.screenshot({ path: 'shot-posts.png', fullPage: true });

// ─── post editor ───
console.log('=== ویرایشگر ===');
await page.click('.btn-primary:has-text("نوشته جدید")');
await page.waitForSelector('.editor-area', { timeout: 8000 });
chk('editor renders', await page.locator('.editor-area').isVisible());
chk('editor toolbar buttons', (await page.locator('.editor-btn').count()) > 8);

await page.fill('input[placeholder="عنوان نوشته"]', 'نوشته آزمایشی از مرورگر');
await page.waitForTimeout(300);
const slug = await page.inputValue('input[placeholder="my-post-slug"]');
chk('slug auto-generated from title', slug.includes('نوشته-آزمایشی'), 'got: ' + slug);

await page.click('.editor-area');
await page.keyboard.type('این متن از طریق مرورگر نوشته شده است.');
await page.waitForTimeout(300);
chk('editor accepts typing', (await page.locator('.editor-area').textContent()).includes('مرورگر'));
chk('word count updates', (await page.locator('.editor-footer').textContent()).includes('کلمه'));
await page.screenshot({ path: 'shot-editor.png', fullPage: true });

// save
await page.click('.toolbar .btn-primary');
await page.waitForSelector('.toast', { timeout: 8000 });
const toastText = await page.locator('.toast-message').first().textContent();
chk('save shows success toast', /ذخیره|ایجاد/.test(toastText), toastText);
await page.waitForTimeout(600);
chk('url switched to edit mode', /#\/posts\/\d+/.test(page.url()), page.url());

// ─── media library ───
console.log('=== رسانه ===');
await page.click('.nav-item:has-text("رسانه")');
await page.waitForSelector('.dropzone', { timeout: 8000 });
chk('media dropzone renders', await page.locator('.dropzone').isVisible());
await page.screenshot({ path: 'shot-media.png' });

// ─── comments ───
console.log('=== دیدگاه‌ها ===');
await page.click('.nav-item:has-text("دیدگاه‌ها")');
await page.waitForTimeout(1500);
chk('comments view loads', await page.locator('.card').first().isVisible());

// ─── categories ───
console.log('=== دسته‌بندی‌ها ===');
await page.click('.nav-item:has-text("دسته‌بندی‌ها")');
await page.waitForSelector('.table', { timeout: 8000 });
chk('categories table renders', (await page.locator('.table tbody tr').count()) >= 3);

// modal
await page.click('.btn-primary:has-text("دسته جدید")');
await page.waitForSelector('.modal', { timeout: 5000 });
chk('modal opens', await page.locator('.modal').isVisible());
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
chk('modal closes on Escape', (await page.locator('.modal').count()) === 0);

// ─── menus ───
console.log('=== فهرست‌ها ===');
await page.click('.nav-item:has-text("فهرست‌ها")');
await page.waitForSelector('.menu-node', { timeout: 8000 });
chk('menu items render', (await page.locator('.menu-node').count()) >= 4);
await page.screenshot({ path: 'shot-menus.png', fullPage: true });

// ─── users ───
console.log('=== کاربران ===');
await page.click('.nav-item:has-text("کاربران")');
await page.waitForSelector('.table', { timeout: 8000 });
chk('users table renders', (await page.locator('.table tbody tr').count()) >= 1);

// ─── settings ───
console.log('=== تنظیمات ===');
await page.click('.nav-item:has-text("تنظیمات")');
await page.waitForSelector('.card-title:has-text("هویت سایت")', { timeout: 8000 });
chk('settings form renders', await page.locator('.card-title:has-text("هویت سایت")').isVisible());
chk('settings tabs render', (await page.locator('.status-filter').count()) >= 4);
await page.screenshot({ path: 'shot-settings.png', fullPage: true });

// ─── profile ───
console.log('=== پروفایل ===');
await page.click('.user-trigger');
await page.waitForTimeout(300);
chk('user dropdown opens', await page.locator('.dropdown').isVisible());
await page.click('.dropdown-item:has-text("پروفایل من")');
await page.waitForSelector('.avatar-lg', { timeout: 8000 });
chk('profile view renders', await page.locator('.avatar-lg').isVisible());
chk('active sessions listed', (await page.locator('.simple-item').count()) >= 1);
await page.screenshot({ path: 'shot-profile.png', fullPage: true });

// ─── responsive ───
console.log('=== واکنش‌گرایی ===');
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(`${BASE}/admin/#/`, { waitUntil: 'networkidle' });
await page.waitForSelector('.stat-grid', { timeout: 8000 });
const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
chk('no horizontal overflow on mobile', !overflow);
await page.screenshot({ path: 'shot-mobile.png', fullPage: true });

await browser.close();

console.log('\n=== خطاهای کنسول ===');
// خطای 401 حاصل تست عمدی رمز اشتباه است و مورد انتظار
const realErrors = errors.filter(e => !/favicon/i.test(e) && !/401/.test(e));
realErrors.forEach(e => console.log('  ' + e));
if (!realErrors.length) console.log('  none');

const failed = results.filter(r => !r.ok).length;
console.log(`\n════ PASS: ${results.length - failed}  FAIL: ${failed}  CONSOLE ERRORS: ${realErrors.length} ════`);
process.exit(failed || realErrors.length ? 1 : 0);
