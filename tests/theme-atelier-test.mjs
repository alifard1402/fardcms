/**
 * تست قالب آتلیه
 *
 * اجرا:  node tests/theme-atelier-test.mjs
 *
 * قالب را موقتاً فعال می‌کند، صفحه‌های اصلی را می‌سنجد و در پایان — حتی
 * اگر تستی رد شود — قالب پیشین را برمی‌گرداند؛ وگرنه بقیه تست‌های سایت
 * روی قالب اشتباه اجرا می‌شدند.
 *
 * داده آزمایشی خودش را می‌سازد و پاک می‌کند، پس به محتوای موجود در
 * دیتابیس وابسته نیست.
 *
 * پیش‌نیاز: سرور توسعه روی 127.0.0.1:8765
 */

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const BASE = 'http://127.0.0.1:8765';
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const results = [];
const errors = [];
const chk = (name, ok, detail = '') => {
    results.push(ok);
    console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${!ok && detail ? ' — ' + detail : ''}`);
};

/** خواندن یا نوشتن قالب فعال از راه CLI، بی‌نیاز از ورود به پنل */
const theme = (value) => execFileSync('php', ['-r',
    value === undefined
        ? 'require "config.php"; echo getOption("active_theme","default");'
        : `require "config.php"; setOption("active_theme", ${JSON.stringify(value)});`,
], { cwd: ROOT, encoding: 'utf8' }).trim();

/** ساخت یا پاک کردن نمونه‌کار آزمایشی */
const fixture = (mode) => execFileSync('php', ['tests/atelier-fixture.php', mode],
    { cwd: ROOT, encoding: 'utf8' }).trim();

const previous = theme();
theme('atelier');
fixture('--create');

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

try {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'fa-IR' });
    const page = await ctx.newPage();

    page.on('pageerror', e => errors.push('pageerror: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });

    /* ─── صفحه اصلی ─── */
    console.log('=== صفحه اصلی ===');
    await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });

    chk('قالب آتلیه بارگذاری شد',
        (await page.locator('link[href*="themes/atelier/assets/style.css"]').count()) === 1);
    chk('شبکه نمونه‌کارها رندر شد', (await page.locator('.work-card').count()) > 0);
    chk('سربرگ روی تصویر شفاف است',
        (await page.locator('.site-header--overlay').count()) === 1
        || (await page.locator('.hero').count()) === 0);

    /* ─── یک نمونه‌کار با گالری ─── */
    console.log('=== نمونه‌کار ===');
    await page.goto(`${BASE}/blog/atelier-test-fixture`, { waitUntil: 'networkidle' });

    chk('عنوان نمونه‌کار نمایش داده شد',
        (await page.locator('.hero-title, .section-title').first().innerText())
            .includes('نمونه‌کار آزمایشی'));
    chk('زیرعنوان و محل عکاسی نمایش داده شد',
        (await page.locator('.hero-text').count()) > 0
        && (await page.locator('.single-facts').innerText()).includes('محل آزمایشی'));
    chk('پخش‌کننده آپارات از شناسه ساخته شد',
        (await page.locator('.video-wrap iframe').getAttribute('src'))
            ?.includes('/videohash/testhash/') === true);

    const shots = await page.locator('.gallery-item').count();
    chk('گالری تصاویر رندر شد', shots > 0, `${shots} تصویر`);

    if (shots > 0) {
        // همه تصویرها باید واقعاً بارگذاری شوند؛ نشانی شکسته بی‌صدا خالی می‌ماند
        const broken = await page.locator('.gallery-item img').evaluateAll(
            list => list.filter(img => !img.complete || img.naturalWidth === 0).length);
        chk('همه تصویرهای گالری بارگذاری شدند', broken === 0, `${broken} تصویر خراب`);

        // هیچ خانه خالی وسط گالری نماند: همه در ردیف‌های پر پشت سر هم‌اند
        const tidy = await page.locator('.gallery').evaluate(el => {
            const items = Array.from(el.children).map(c => c.getBoundingClientRect());
            const cols = new Set(items.map(r => Math.round(r.left))).size;
            const rows = new Set(items.map(r => Math.round(r.top))).size;

            return items.length >= (rows - 1) * cols + 1;
        });
        chk('گالری خانه خالی وسط ندارد', tidy);

        /* ─── لایت‌باکس ─── */
        await page.locator('.gallery-item').first().click();
        await page.waitForTimeout(400);
        chk('لایت‌باکس باز شد', await page.locator('#lightbox').isVisible());
        chk('سربرگ پشت لایت‌باکس پنهان شد',
            (await page.locator('#site-header').evaluate(el => getComputedStyle(el).visibility)) === 'hidden');

        const first = await page.locator('#lightbox-image').getAttribute('src');
        await page.keyboard.press('ArrowLeft');
        await page.waitForTimeout(300);
        const second = await page.locator('#lightbox-image').getAttribute('src');
        chk('کلید جهت‌دار تصویر بعدی را می‌آورد', shots === 1 || first !== second);

        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        chk('با Escape بسته شد', !(await page.locator('#lightbox').isVisible()));
    }

    /* ─── موبایل ─── */
    console.log('=== موبایل ===');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });

    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth + 2);
    chk('بدون سرریز افقی', !overflow);

    await page.click('#nav-toggle');
    await page.waitForTimeout(450);

    // در راست‌به‌چپ کشو باید از سمت راست بیاید و کامل داخل صفحه بنشیند
    const box = await page.locator('#site-nav').boundingBox();
    chk('کشوی منو از سمت راست کامل باز می‌شود',
        box !== null && Math.abs(box.x + box.width - 390) < 2,
        JSON.stringify(box));

    await page.click('#nav-toggle');
    await page.waitForTimeout(450);
    chk('کشوی منو بسته می‌شود',
        (await page.locator('#site-nav').evaluate(el => getComputedStyle(el).visibility)) === 'hidden');

    /* ─── بقیه صفحه‌ها ─── */
    console.log('=== بقیه صفحه‌ها ===');
    await page.setViewportSize({ width: 1280, height: 900 });

    // صفحه ۴۰۴ عمداً آزموده می‌شود و خودِ آن یک خطای شبکه در کنسول ثبت
    // می‌کند؛ خطاهای واقعی تا اینجا شمرده شده‌اند.

    for (const [label, path, selector] of [
        ['آرشیو دسته', 'category/uncategorized', '.section-title'],
        ['جستجو', 'search?q=a', '.search-input'],
        ['۴۰۴', 'no-such-page-here', '.big-code'],
    ]) {
        const response = await page.goto(`${BASE}/${path}`, { waitUntil: 'networkidle' });
        const ok = (await page.locator(selector).count()) > 0;
        chk(`${label} رندر شد`, ok, `status ${response?.status()}`);
    }
} finally {
    await browser.close();
    fixture('--remove');
    theme(previous);
    console.log(`\nقالب به «${previous}» برگردانده شد.`);
}

// درخواست ناموفق به آپارات (بدون اینترنت) و خطای ۴۰۴ صفحه‌ای که عمداً
// وجود ندارد، خطای قالب نیستند
const real = errors.filter(e => !/aparat\.com/.test(e) && !/404 \(Not Found\)/.test(e));
console.log('خطاهای کنسول:', real.length ? real : 'هیچ');

const failed = results.filter(r => !r).length;
console.log(`\n════ PASS: ${results.length - failed}  FAIL: ${failed} ════`);
process.exit(failed || real.length ? 1 : 0);
