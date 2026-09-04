<?php
/**
 * تست پاک‌سازی HTML
 *
 * اجرا:  php tests/sanitizer-test.php
 *
 * این تست مرز اصلی امنیتی سیستم را می‌سنجد: محتوایی که کاربران دارای
 * دسترسی نوشتن ذخیره می‌کنند و بعداً برای همه بازدیدکنندگان رندر می‌شود.
 */

require dirname(__DIR__) . '/config.php';

$pass = 0;
$fail = 0;

/**
 * محتوا باید پاک شود: هیچ نشانه‌ای از اجرای کد در خروجی نماند
 */
function mustBeSafe(string $name, string $payload): void
{
    global $pass, $fail;

    $clean = sanitizeHtml($payload);

    // خروجی باید پس از رمزگشایی موجودیت‌ها هم بی‌خطر باشد
    $decoded = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = preg_replace('/[\x00-\x20]/', '', $decoded) ?? '';

    $problems = [];

    if (preg_match('/<\s*(script|iframe|object|embed|form|base|meta|link|style|svg|math|noscript)\b/i', $clean)) {
        $problems[] = 'عنصر خطرناک';
    }

    if (preg_match('/\son[a-z]+\s*=/i', ' ' . $clean) || preg_match('/[\/"\']on[a-z]+\s*=/i', $clean)) {
        $problems[] = 'ویژگی رویداد';
    }

    if (preg_match('/(javascript|vbscript):/i', $decoded)) {
        $problems[] = 'پروتکل اجرایی';
    }

    if (preg_match('/data\s*:\s*text\/html/i', $decoded)) {
        $problems[] = 'data:text/html';
    }

    if (preg_match('/\b(srcset|formaction|autofocus|style|ontoggle|onload|onerror)\b/i', $clean)) {
        $problems[] = 'ویژگی غیرمجاز';
    }

    if (empty($problems)) {
        $pass++;
        printf("  PASS  %s\n", $name);
    } else {
        $fail++;
        printf("  FAIL  %s — %s\n        خروجی: %s\n",
            $name, implode(', ', $problems), mb_substr($clean, 0, 120, 'UTF-8'));
    }
}

/**
 * محتوای مجاز باید حفظ شود
 *
 * @param string[] $expected رشته‌هایی که باید در خروجی باشند
 */
function mustKeep(string $name, string $payload, array $expected): void
{
    global $pass, $fail;

    $clean = sanitizeHtml($payload);
    $missing = array_values(array_filter($expected, fn($needle) => !str_contains($clean, $needle)));

    if (empty($missing)) {
        $pass++;
        printf("  PASS  %s\n", $name);
    } else {
        $fail++;
        printf("  FAIL  %s — گم شد: %s\n        خروجی: %s\n",
            $name, implode(' | ', $missing), mb_substr($clean, 0, 140, 'UTF-8'));
    }
}

echo "═══ حمله‌های تزریق اسکریپت ═══\n";
mustBeSafe('اسکریپت ساده',            '<script>alert(1)</script>');
mustBeSafe('اسکریپت با ویژگی',        '<script type="text/javascript">alert(1)</script>');
mustBeSafe('اسکریپت تودرتو',          '<scr<script>ipt>alert(1)</scr</script>ipt>');
mustBeSafe('اسکریپت با حروف بزرگ',    '<ScRiPt>alert(1)</ScRiPt>');
mustBeSafe('اسکریپت با فاصله',        '<script >alert(1)</script >');
mustBeSafe('اسکریپت بدون بستن',       '<script>alert(1)');

echo "\n═══ ویژگی‌های رویداد ═══\n";
mustBeSafe('onerror با فاصله',        '<img src=x onerror=alert(1)>');
mustBeSafe('onerror بدون فاصله',      '<img src="x"onerror="alert(1)">');
mustBeSafe('onload با اسلش',          '<svg/onload=alert(1)>');
mustBeSafe('onload با خط جدید',       "<svg\nonload=alert(1)>");
mustBeSafe('onload با tab',           "<body\tonload=alert(1)>");
mustBeSafe('ontoggle',                '<details open ontoggle=alert(1)>');
mustBeSafe('onfocus + autofocus',     '<input autofocus onfocus=alert(1)>');
mustBeSafe('onanimationstart',        '<div onanimationstart=alert(1)>x</div>');
mustBeSafe('onerror روی source',      '<video><source onerror=alert(1)></video>');
mustBeSafe('رویداد با حروف بزرگ',     '<img src=x ONERROR=alert(1)>');

echo "\n═══ پروتکل‌های اجرایی در آدرس ═══\n";
mustBeSafe('javascript ساده',         '<a href="javascript:alert(1)">x</a>');
mustBeSafe('javascript با موجودیت',   '<a href="java&#115;cript:alert(1)">x</a>');
mustBeSafe('javascript با tab',       '<a href="jav&#x09;ascript:alert(1)">x</a>');
mustBeSafe('javascript با خط جدید',   "<a href=\"java\nscript:alert(1)\">x</a>");
mustBeSafe('javascript با فاصله',     '<a href="  javascript:alert(1)">x</a>');
mustBeSafe('javascript حروف بزرگ',    '<a href="JaVaScRiPt:alert(1)">x</a>');
mustBeSafe('vbscript',                '<a href="vbscript:msgbox(1)">x</a>');
mustBeSafe('data text/html',          '<a href="data:text/html,<script>alert(1)</script>">x</a>');
mustBeSafe('formaction',              '<button formaction="javascript:alert(1)">x</button>');
mustBeSafe('srcset با data',          '<img srcset="data:text/html,<script>alert(1)</script>">');
mustBeSafe('poster اجرایی',           '<video poster="javascript:alert(1)"></video>');

echo "\n═══ عناصر خطرناک ═══\n";
mustBeSafe('iframe',                  '<iframe src="//evil.com"></iframe>');
mustBeSafe('iframe srcdoc',           '<iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe>');
mustBeSafe('object',                  '<object data="javascript:alert(1)"></object>');
mustBeSafe('embed',                   '<embed src="//evil.com/x.swf">');
mustBeSafe('form',                    '<form action="//evil.com"><input name=p></form>');
mustBeSafe('meta refresh',            '<meta http-equiv="refresh" content="0;url=//evil.com">');
mustBeSafe('base href',               '<base href="//evil.com/">');
mustBeSafe('link stylesheet',         '<link rel="stylesheet" href="//evil.com/x.css">');
mustBeSafe('style بلوکی',             '<style>body{background:url(javascript:alert(1))}</style>');
mustBeSafe('ویژگی style',             '<div style="background:url(javascript:alert(1))">x</div>');
mustBeSafe('template',                '<template><img src=x onerror=alert(1)></template>');

echo "\n═══ حمله‌های mXSS ═══\n";
mustBeSafe('noscript',                '<noscript><p title="</noscript><img src=x onerror=alert(1)>">');
mustBeSafe('math + mglyph',           '<math><mtext><table><mglyph><style><!--</style><img src=1 onerror=alert(1)>');
mustBeSafe('توضیح شرطی',              '<!--[if IE]><script>alert(1)</script><![endif]-->');
mustBeSafe('CDATA',                   '<![CDATA[<script>alert(1)</script>]]>');
mustBeSafe('svg foreignObject',       '<svg><foreignObject><script>alert(1)</script></foreignObject></svg>');
mustBeSafe('بایت تهی',                "<img src=x on\0error=alert(1)>");

echo "\n═══ حفظ محتوای مجاز ═══\n";
mustKeep('متن فارسی و تأکید',
    '<p>سلام <strong>دنیا</strong>، این یک <em>آزمایش</em> است.</p>',
    ['سلام', '<strong>دنیا</strong>', '<em>آزمایش</em>']);

mustKeep('عنوان و فهرست',
    '<h2>عنوان</h2><ul><li>یک</li><li>دو</li></ul><ol start="3"><li>سه</li></ol>',
    ['<h2>عنوان</h2>', '<li>یک</li>', '<ol start="3">']);

mustKeep('پیوند امن',
    '<a href="https://example.com" title="نمونه">پیوند</a>',
    ['href="https://example.com"', 'title="نمونه"', 'پیوند']);

mustKeep('پیوند نسبی',
    '<a href="/blog/my-post">نوشته</a><a href="#section">لنگر</a><a href="?page=2">صفحه</a>',
    ['href="/blog/my-post"', 'href="#section"', 'href="?page=2"']);

// نامک فارسی در خروجی به شکل percent-encoded سریال می‌شود؛ این استاندارد
// URI است و کنترلر سایت با urldecode آن را درست می‌خواند
mustKeep('نامک فارسی در آدرس',
    '<a href="/blog/سلام-دنیا">نوشته فارسی</a>',
    ['/blog/%D8%B3%D9%84%D8%A7%D9%85-%D8%AF%D9%86%DB%8C%D8%A7', 'نوشته فارسی']);

mustKeep('mailto و tel',
    '<a href="mailto:a@b.com">ایمیل</a><a href="tel:+982112345678">تلفن</a>',
    ['mailto:a@b.com', 'tel:+982112345678']);

mustKeep('تصویر',
    '<img src="/uploads/2026/09/a.png" alt="تصویر نمونه" width="800" height="600">',
    ['src="/uploads/2026/09/a.png"', 'alt="تصویر نمونه"', 'width="800"']);

// در محتوای متنی، فرار دادن < و & و > لازم و کافی است؛ گیومه در این
// جایگاه معنای نشانه‌گذاری ندارد و خام می‌ماند
mustKeep('نقل‌قول و کد',
    '<blockquote><p>نقل‌قول</p></blockquote><pre><code>echo "test";</code></pre>',
    ['<blockquote>', '<pre>', '<code>', 'echo "test";']);

mustKeep('فرار دادن کاراکترهای نشانه‌گذاری در متن',
    '<pre><code>if (a &lt; b &amp;&amp; c) { }</code></pre><p>x &lt; y</p>',
    ['&lt;', '&amp;']);

mustKeep('جدول',
    '<table><thead><tr><th scope="col">نام</th></tr></thead><tbody><tr><td colspan="2">مقدار</td></tr></tbody></table>',
    ['<table>', 'scope="col"', 'colspan="2"', 'مقدار']);

mustKeep('figure و figcaption',
    '<figure><img src="/a.jpg" alt="ت"><figcaption>توضیح تصویر</figcaption></figure>',
    ['<figure>', '<figcaption>توضیح تصویر</figcaption>']);

mustKeep('target امن می‌شود',
    '<a href="https://example.com" target="_blank">بیرونی</a>',
    ['target="_blank"', 'rel="noopener noreferrer"']);

mustKeep('عنصر غیرمجاز، متن می‌ماند',
    '<marquee>متن مهم</marquee>',
    ['متن مهم']);

echo "\n═══ پاک‌سازی درون‌خطی (متن پاورقی) ═══\n";
$inline = sanitizeInlineHtml('<p>پاراگراف</p><a href="https://ok.com">پیوند</a><script>alert(1)</script><strong>مهم</strong>');
$inlineOk = str_contains($inline, 'https://ok.com')
    && str_contains($inline, '<strong>مهم</strong>')
    && !str_contains($inline, '<script')
    && !str_contains($inline, '<p>');
printf("  %s  فقط عناصر درون‌خطی مجازند\n", $inlineOk ? 'PASS' : 'FAIL');
$inlineOk ? $pass++ : $fail++;

echo "\n═══ حالت‌های مرزی ═══\n";
foreach ([['خالی', ''], ['فقط فاصله', "  \n\t "], ['متن ساده', 'فقط متن بدون تگ']] as [$label, $input]) {
    $result = sanitizeHtml($input);
    $ok = is_string($result);
    printf("  %s  %s → «%s»\n", $ok ? 'PASS' : 'FAIL', $label, mb_substr($result, 0, 40, 'UTF-8'));
    $ok ? $pass++ : $fail++;
}

// محتوای بزرگ نباید باعث خطا یا کندی غیرعادی شود
$big = str_repeat('<p>پاراگراف نمونه با <strong>تأکید</strong> و <a href="/x">پیوند</a>.</p>', 400);
$start = microtime(true);
$result = sanitizeHtml($big);
$elapsed = microtime(true) - $start;
$bigOk = str_contains($result, '<strong>تأکید</strong>') && $elapsed < 3.0;
printf("  %s  محتوای بزرگ (۴۰۰ پاراگراف) در %.2f ثانیه\n", $bigOk ? 'PASS' : 'FAIL', $elapsed);
$bigOk ? $pass++ : $fail++;

echo "\n════ PASS: $pass  FAIL: $fail ════\n";
exit($fail > 0 ? 1 : 0);
