/**
 * تست مودال‌ها روی موبایل و انتخابگر فایل افزونه
 *
 * اجرا:  node tests/modal-mobile-test.mjs
 *
 * چرا لازم است: کارت‌ها در پوسته تیره backdrop-filter دارند و هر عنصری
 * که backdrop-filter داشته باشد برای فرزندان position:fixed خود «قاب
 * مرجع» می‌سازد. مودالی که داخل یک کارت رندر می‌شد به‌جای پوشاندن صفحه،
 * داخل همان کارت می‌نشست و overflow:hidden کارت هم می‌بریدش. روی دسکتاپ
 * کارت پهن بود و تقریباً درست به نظر می‌رسید؛ روی موبایل کاملاً
 * غیرقابل استفاده می‌شد. حالا مودال با Teleport به body می‌رود.
 *
 * ضمناً می‌سنجد که فیلدهای فایلِ افزونه، نشانی دستی نمی‌خواهند و
 * کتابخانه رسانه را باز می‌کنند.
 *
 * پیش‌نیاز: سرور توسعه و فعال بودن افزونه گالری
 */

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const php = (code) => execFileSync('php', ['-r', code], { cwd: ROOT, encoding: 'utf8' }).trim();

const previousPlugins = php('require "config.php"; echo json_encode(getOption("active_plugins",[]));');
php('require "config.php"; setOption("active_plugins",["gallery"]);');

const B = 'http://127.0.0.1:8765';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, locale: 'fa-IR',
                                 isMobile: true, hasTouch: true });
const p = await ctx.newPage();
let bad = 0;
const chk = (n, ok, d = '') => { if (!ok) bad++; console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${n}${!ok && d ? ' — ' + d : ''}`); };

await p.goto(`${B}/login.php`);
await p.fill('#login', 'admin');
await p.fill('#password', 'Admin@1234');
await p.click('.submit-btn');
await p.waitForURL(/admin/, { timeout: 15000 });
await p.goto(`${B}/admin/#/posts/new`, { waitUntil: 'networkidle' });
await p.waitForSelector('.card-title:text-is("گالری تصاویر")', { timeout: 10000 });

/** مودال باید کل صفحه را بپوشاند، نه اینکه داخل کارت گیر کند */
async function checkModal(label) {
  await p.waitForSelector('.modal-backdrop', { timeout: 6000 });
  await p.waitForTimeout(400);

  const box = await p.locator('.modal-backdrop').boundingBox();
  const parent = await p.locator('.modal-backdrop').evaluate(
    el => el.parentElement.tagName + (el.closest('.card') ? ' (داخل کارت!)' : ''));

  chk(`${label}: مودال تمام‌صفحه است`,
      box !== null && box.width >= 388 && box.height >= 840,
      JSON.stringify(box));
  chk(`${label}: مودال بیرون از کارت رندر شده`, !parent.includes('کارت'), parent);

  const modal = await p.locator('.modal').boundingBox();
  chk(`${label}: پنجره داخل صفحه جا می‌شود`,
      modal !== null && modal.x >= -1 && modal.x + modal.width <= 391,
      JSON.stringify(modal));

  await p.locator('.modal-header .icon-btn').click();
  await p.waitForTimeout(400);
}

// تصویر شاخص
await p.locator('.card:has(.card-title:text-is("تصویر شاخص")) .dropzone').click();
await checkModal('featured');

// گالری افزونه
await p.locator('.card:has(.card-title:text-is("گالری تصاویر")) button:has-text("افزودن تصویر")').click();
await checkModal('gallery');

// ویدیو: نشانی دستی نباید باشد، باید انتخابگر باز شود
const videoCard = p.locator('.card:has(.card-title:text-is("ویدیو"))');
await videoCard.locator('select').selectOption('file');
await p.waitForTimeout(300);

chk('فیلد نشانی دستی ویدیو حذف شد',
    (await videoCard.locator('input[type="text"]').count()) === 0);
chk('دکمه انتخاب ویدیو از کتابخانه هست',
    await videoCard.locator('button:has-text("انتخاب ویدیو از کتابخانه")').isVisible());

await videoCard.locator('button:has-text("انتخاب ویدیو از کتابخانه")').click();
await checkModal('video');

chk('دکمه انتخاب تصویر پیش‌نمایش هست',
    await videoCard.locator('button:has-text("انتخاب تصویر پیش‌نمایش")').isVisible());

await b.close();
php(`require "config.php"; setOption("active_plugins", json_decode(${JSON.stringify(previousPlugins)}, true));`);

console.log(`\n════ PASS: ${12 - bad}  FAIL: ${bad} ════`);
process.exit(bad ? 1 : 0);
