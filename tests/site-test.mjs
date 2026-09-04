import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8765';
const errors = [];
const results = [];
function chk(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${!ok && detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 }, locale: 'fa-IR' });
const page = await ctx.newPage();
// بارگذاری عمدی صفحه ۴۰۴ خودش یک خطای کنسول تولید می‌کند و مورد انتظار است
let expect404 = false;
page.on('console', m => {
  if (m.type() === 'error' && !(expect404 && /404/.test(m.text()))) {
    errors.push('console: ' + m.text());
  }
});
page.on('pageerror', e => errors.push('pageerror: ' + e.message));
page.on('requestfailed', r => { if(!/favicon/.test(r.url())) errors.push('reqfail: ' + r.url()); });

console.log('=== صفحه اصلی ===');
await page.goto(BASE, { waitUntil: 'networkidle' });
chk('header renders', await page.locator('.site-header').isVisible());
chk('brand name shown', (await page.locator('.brand-name').first().textContent()).length > 0);
chk('nav menu has items', (await page.locator('.nav-menu a').count()) >= 4);
chk('post cards render', (await page.locator('.post-card').count()) >= 2);
chk('footer renders', await page.locator('.site-footer').isVisible());
chk('font loaded', await page.evaluate(() => document.fonts.check('16px Vazirmatn')));
chk('rtl direction', await page.evaluate(() => document.documentElement.dir === 'rtl'));
chk('no horizontal overflow', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2));
await page.screenshot({ path: 'site-home.png', fullPage: true });

console.log('=== پوسته تیره/روشن ===');
const initial = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
await page.click('#theme-toggle');
await page.waitForTimeout(400);
const after = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
chk('theme toggles', initial !== after, `${initial} -> ${after}`);
await page.screenshot({ path: 'site-home-alt.png' });
await page.click('#theme-toggle');
await page.waitForTimeout(300);

console.log('=== جستجو در سربرگ ===');
await page.click('#search-toggle');
await page.waitForTimeout(300);
chk('search box opens', await page.locator('#header-search.is-open').isVisible());
await page.fill('#search-field', 'راهنما');
await page.press('#search-field', 'Enter');
await page.waitForLoadState('networkidle');
chk('search navigates', page.url().includes('/search'));
chk('search results found', (await page.locator('.post-card').count()) >= 1);
await page.screenshot({ path: 'site-search.png' });

console.log('=== نوشته تکی ===');
await page.goto(`${BASE}/blog/welcome-to-fardcms`, { waitUntil: 'networkidle' });
chk('post title renders', (await page.locator('.single-title').textContent()).length > 5);
chk('content renders', (await page.locator('.content').textContent()).length > 100);
chk('content has headings', (await page.locator('.content h2, .content h3').count()) >= 1);
chk('meta shows date', (await page.locator('.single-meta').textContent()).length > 5);
chk('comment form present', await page.locator('#comment-form').isVisible());
chk('og:title meta tag', (await page.locator('meta[property="og:title"]').getAttribute('content')).length > 0);
chk('json-ld schema present', (await page.locator('script[type="application/ld+json"]').count()) === 1);
chk('canonical link', (await page.locator('link[rel=canonical]').getAttribute('href')).includes('welcome'));
await page.screenshot({ path: 'site-single.png', fullPage: true });

console.log('=== ارسال دیدگاه ===');
await page.fill('#author_name', 'کاربر آزمایشی');
await page.fill('#author_email', `visitor${Date.now()}@example.com`);
// متن یکتا لازم است: نگهبان دیدگاه تکراری، ارسال یکسان را تا ۱۰ دقیقه رد می‌کند
await page.fill('#comment-content', `دیدگاه آزمایشی از مرورگر — شناسه ${Date.now()}`);
await page.waitForTimeout(200);
chk('char counter updates', (await page.locator('#comment-counter').textContent()).trim() !== '۰');
await page.click('#comment-submit');
await page.waitForSelector('#comment-success:visible', { timeout: 8000 });
chk('comment submitted', (await page.locator('#comment-success-text').textContent()).length > 5);

console.log('=== برگه ===');
await page.goto(`${BASE}/about`, { waitUntil: 'networkidle' });
chk('page renders', (await page.locator('.single-title').textContent()).includes('درباره'));

console.log('=== آرشیو دسته ===');
await page.goto(`${BASE}/category/tutorials`, { waitUntil: 'networkidle' });
chk('category archive renders', (await page.locator('.page-heading').textContent()).length > 0);
chk('category posts listed', (await page.locator('.post-card').count()) >= 1);

console.log('=== آرشیو نویسنده ===');
await page.goto(`${BASE}/author/admin`, { waitUntil: 'networkidle' });
chk('author archive renders', (await page.locator('.page-heading').textContent()).includes('نوشته'));

console.log('=== صفحه ۴۰۴ ===');
expect404 = true;
const resp404 = await page.goto(`${BASE}/this-page-does-not-exist`, { waitUntil: 'networkidle' });
chk('404 status code', resp404.status() === 404, String(resp404.status()));
chk('404 page renders', (await page.locator('.big-code').textContent()).includes('۴۰۴'));
await page.screenshot({ path: 'site-404.png' });

console.log('=== RSS و نقشه سایت ===');
const feed = await page.goto(`${BASE}/feed`);
chk('feed content-type xml', (feed.headers()['content-type'] || '').includes('xml'));
const feedBody = await feed.text();
chk('feed has items', (feedBody.match(/<item>/g) || []).length >= 2);
const sm = await page.goto(`${BASE}/sitemap.xml`);
chk('sitemap has urls', ((await sm.text()).match(/<url>/g) || []).length >= 4);

// پیام کنسول صفحه ۴۰۴ ممکن است با تأخیر برسد، پس تا این نقطه نادیده گرفته می‌شود
expect404 = false;
console.log('=== موبایل ===');
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(BASE, { waitUntil: 'networkidle' });
chk('mobile no overflow', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2));
chk('nav toggle visible', await page.locator('#nav-toggle').isVisible());
await page.click('#nav-toggle');
await page.waitForTimeout(300);
chk('mobile nav opens', await page.locator('#site-nav.is-open').isVisible());
await page.screenshot({ path: 'site-mobile.png', fullPage: true });

await page.setViewportSize({ width: 1360, height: 900 });
await page.goto(`${BASE}/blog/welcome-to-fardcms`, { waitUntil: 'networkidle' });
await page.screenshot({ path: 'site-single-comments.png', fullPage: true });

await browser.close();

console.log('\n=== خطاهای کنسول ===');
errors.forEach(e => console.log('  ' + e));
if (!errors.length) console.log('  none');

const failed = results.filter(r => !r.ok).length;
console.log(`\n════ PASS: ${results.length - failed}  FAIL: ${failed}  ERRORS: ${errors.length} ════`);
process.exit(failed || errors.length ? 1 : 0);
