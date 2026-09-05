/**
 * تست خودترمیمی استایل پنل مدیریت
 *
 * اجرا:  node tests/cache-heal-test.mjs
 *
 * چرا لازم است: روی هاست‌های اشتراکی و پشت CDN دیده شد که کاربر فایل‌های
 * نسخه تازه را درست آپلود می‌کند، ولی مرورگر (یا CDN) همچنان admin.css
 * قدیمی را تحویل می‌دهد. چون خودِ index.html هم کش شده بود، مهر ?v= تازه
 * هرگز به مرورگر نمی‌رسید تا کش را بشکند. نتیجه: کاربر اصلاح ظاهری را
 * نمی‌دید و گمان می‌کرد رفع اشکال انجام نشده است.
 *
 * حالا admin.css شناسه --fardcms-css دارد و app.js — که همیشه بازبینی
 * می‌شود — آن را می‌سنجد و در صورت کهنه بودن، فایل را با نشانی تازه
 * دوباره می‌گیرد. این تست دقیقاً همان وضعیت را می‌سازد: نخستین درخواست
 * CSS نسخه قدیمی و خرابِ ۱.۰.۴ را می‌گیرد (کش)، درخواست بعدی نسخه واقعی
 * سرور را.
 */

import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const BASE = 'http://127.0.0.1:8765';
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const results = [];
const chk = (name, ok, detail = '') => {
    results.push(ok);
    console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${!ok && detail ? ' — ' + detail : ''}`);
};

const realCss = readFileSync(join(ROOT, 'admin/assets/css/admin.css'), 'utf8');

// بازسازی نسخه معیوب ۱.۰.۴: بدون شناسه نسخه و با همان اشکال راست‌به‌چپی
// که دستگیره کلید را بیرون از ریل می‌انداخت.
const staleCss = realCss
    .replace(/:root \{ --fardcms-css: "[\d.]+"; \}/, '')
    .replace(/(\.switch::after \{[^}]*?)right: 3px;\n  left: auto;/s, '$1inset-inline-end: 3px;')
    .replace(/html\[dir="ltr"\] \.switch::after \{[^}]*\}/, '')
    .replace(/html\[dir="ltr"\] \.switch\.on::after \{[^}]*\}/, '');

if (staleCss === realCss || staleCss.includes('--fardcms-css')) {
    console.error('ساخت نسخه کهنه شبیه‌سازی‌شده شکست خورد — تست بی‌معنا می‌شود');
    process.exit(1);
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await browser.newContext({ viewport: { width: 900, height: 1000 }, locale: 'fa-IR' });
const page = await ctx.newPage();

// نخستین درخواست admin.css نسخه کهنه را می‌گیرد؛ بقیه از سرور واقعی
let cssRequests = 0;
await page.route('**/admin.css*', async route => {
    cssRequests++;

    if (cssRequests === 1) {
        return route.fulfill({ status: 200, contentType: 'text/css', body: staleCss });
    }

    return route.continue();
});

await page.goto(`${BASE}/login.html`);
await page.fill('#login', 'admin');
await page.fill('#password', 'Admin@1234');
await page.click('.submit-btn');
await page.waitForURL(/admin\//, { timeout: 15000 });
await page.waitForSelector('.stat-grid', { timeout: 15000 });
await page.waitForTimeout(1200);

chk('استایل کهنه شناسایی و دوباره گرفته شد', cssRequests >= 2, `${cssRequests} درخواست`);

const applied = await page.evaluate(() =>
    getComputedStyle(document.documentElement)
        .getPropertyValue('--fardcms-css').trim().replace(/^["']|["']$/g, ''));
const expected = /EXPECTED_CSS_VERSION\s*=\s*'([\d.]+)'/.exec(
    readFileSync(join(ROOT, 'admin/assets/js/app.js'), 'utf8'))[1];

chk('نسخه استایلِ اعمال‌شده به‌روز است', applied === expected, `${applied || 'ندارد'} ≠ ${expected}`);

// تنها یک برگ استایل admin.css باید در صفحه بماند
const linkCount = await page.locator('link[rel="stylesheet"][href*="admin.css"]').count();
chk('برگ استایل کهنه از صفحه حذف شد', linkCount === 1, `${linkCount} تگ link`);

// و مهم‌تر: ظاهر واقعاً درست شده باشد — دستگیره کلید داخل ریل
await page.goto(`${BASE}/admin/#/settings`, { waitUntil: 'networkidle' });
await page.click('.status-filter:has-text("گفت‌وگو")');
await page.waitForSelector('.switch', { timeout: 8000 });

const knob = await page.locator('.switch').first().evaluate(el => {
    const cs = getComputedStyle(el, '::after');
    const m = cs.transform.match(/matrix\(([^)]+)\)/);
    const dx = m ? parseFloat(m[1].split(',')[4]) : 0;
    const left = parseFloat(cs.left) + dx;

    return { left, right: left + parseFloat(cs.width), track: el.getBoundingClientRect().width };
});

chk('دستگیره کلید داخل ریل است', knob.left >= -1 && knob.right <= knob.track + 1,
    `[${knob.left}..${knob.right}] در ریل ${knob.track}`);

await browser.close();

const failed = results.filter(r => !r).length;
console.log(`\n════ PASS: ${results.length - failed}  FAIL: ${failed} ════`);
process.exit(failed ? 1 : 0);
