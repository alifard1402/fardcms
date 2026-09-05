/**
 * تست چرخه فعال و غیرفعال کردن افزونه از داخل پنل
 *
 * اجرا:  node tests/plugin-cycle-test.mjs
 *
 * چیزی که می‌سنجد: با غیرفعال شدن افزونه گالری، هم پنل ویرایشگر و هم
 * سایت عمومی باید بی‌سروصدا و بدون خطا از آن صرف‌نظر کنند — نه خطای
 * «تابع تعریف‌نشده». همین معیارِ واقعیِ مستقل بودن قالب از افزونه است.
 *
 * پیش‌نیاز: سرور توسعه، قالب atelier نصب، و داده آزمایشی
 * (tests/atelier-fixture.php --create)
 */

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

/** وضعیت شناخته‌شده: افزونه فعال، قالب آتلیه، داده آزمایشی موجود */
const php = (code) => execFileSync('php', ['-r', code], { cwd: ROOT, encoding: 'utf8' }).trim();

const previousTheme = php('require "config.php"; echo getOption("active_theme","default");');
const previousPlugins = php('require "config.php"; echo json_encode(getOption("active_plugins",[]));');

php('require "config.php"; setOption("active_plugins",["gallery"]); setOption("active_theme","atelier");');
execFileSync('php', ['tests/atelier-fixture.php', '--create'], { cwd: ROOT });

const B = 'http://127.0.0.1:8765';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 1400, height: 950 }, locale: 'fa-IR' });
const p = await ctx.newPage();
let bad = 0;
const chk = (n, ok, d = '') => { if (!ok) bad++; console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${n}${!ok && d ? ' — ' + d : ''}`); };

await p.goto(`${B}/login.php`);
await p.fill('#login', 'admin');
await p.fill('#password', 'Admin@1234');
await p.click('.submit-btn');
await p.waitForURL(/admin/, { timeout: 15000 });
await p.waitForSelector('.stat-grid', { timeout: 15000 });

const pluginRow = () => p.locator('.simple-item', { hasText: 'گالری و ویدیو' });

const gotoPlugins = async () => {
  await p.goto(`${B}/admin/#/extensions`, { waitUntil: 'networkidle' });
  await p.click('.status-filter:has-text("افزونه‌ها")');
  await p.waitForSelector('.simple-item', { timeout: 8000 });
};

const editorCards = async () => {
  await p.goto(`${B}/admin/#/posts/new`, { waitUntil: 'networkidle' });
  await p.waitForSelector('.card-title', { timeout: 8000 });
  await p.waitForTimeout(400);
  return p.locator('.card-title').allInnerTexts();
};

// ─── غیرفعال کردن ───
await gotoPlugins();
await pluginRow().locator('button:has-text("غیرفعال کردن")').click();
await p.waitForTimeout(900);
chk('افزونه غیرفعال شد', (await pluginRow().locator('button:has-text("فعال کردن")').count()) === 1);

// پنل باید از نو بارگذاری شود تا اسکریپت افزونه دیگر تزریق نشود
await p.goto(`${B}/admin/`, { waitUntil: 'networkidle' });
await p.waitForSelector('.stat-grid', { timeout: 15000 });
let cards = await editorCards();
chk('پنل گالری با غیرفعال شدن افزونه ناپدید شد', !cards.some(t => t.includes('گالری')), cards.join(' | '));

// سایت هم نباید بشکند
const site = await p.goto(`${B}/blog/atelier-test-fixture`, { waitUntil: 'networkidle' });
chk('سایت بدون افزونه هم باز می‌شود', site?.status() === 200, String(site?.status()));
chk('گالری در سایت نمایش داده نمی‌شود', (await p.locator('.gallery-item').count()) === 0);

// ─── دوباره فعال ───
await p.goto(`${B}/admin/`, { waitUntil: 'networkidle' });
await gotoPlugins();
await pluginRow().locator('button:has-text("فعال کردن")').click();
await p.waitForTimeout(900);
chk('افزونه دوباره فعال شد', (await pluginRow().locator('button:has-text("غیرفعال کردن")').count()) === 1);

await p.goto(`${B}/admin/`, { waitUntil: 'networkidle' });
await p.waitForSelector('.stat-grid', { timeout: 15000 });
cards = await editorCards();
chk('پنل گالری برگشت', cards.some(t => t.includes('گالری')), cards.join(' | '));

await p.goto(`${B}/blog/atelier-test-fixture`, { waitUntil: 'networkidle' });
chk('گالری در سایت برگشت', (await p.locator('.gallery-item').count()) > 0);

await b.close();

// بازگرداندن وضعیت، وگرنه بقیه تست‌ها روی قالب و افزونه اشتباه اجرا می‌شوند
execFileSync('php', ['tests/atelier-fixture.php', '--remove'], { cwd: ROOT });
php(`require "config.php"; setOption("active_theme", ${JSON.stringify(previousTheme)});`
    + ` setOption("active_plugins", json_decode(${JSON.stringify(previousPlugins)}, true));`);

console.log(bad ? `\n${bad} مورد رد شد` : '\nهمه قبول');
console.log(`\n════ PASS: ${7 - bad}  FAIL: ${bad} ════`);
process.exit(bad ? 1 : 0);
