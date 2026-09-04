<?php
/**
 * پاک‌سازی HTML بر پایه DOM و فهرست سفید
 *
 * چرا DOM و نه عبارت باقاعده؟ پاک‌سازی HTML با regex در برابر مهاجم
 * جدی شکست می‌خورد؛ نمونه‌هایی مانند `<svg/onload=…>`،
 * `<img src="x"onerror="…">` یا `href="java&#115;cript:…"` از فیلترهای
 * متنی رد می‌شوند اما مرورگر آن‌ها را اجرا می‌کند. اینجا سند واقعاً
 * تجزیه می‌شود و هر عنصر و ویژگی‌ای که در فهرست سفید نباشد حذف می‌شود.
 */

/**
 * عناصر مجاز و ویژگی‌های مجاز هر یک
 *
 * @return array<string, string[]>
 */
function allowedHtmlElements(): array
{
    return [
        // متن و ساختار
        'p' => [], 'br' => [], 'hr' => [],
        'div' => [], 'span' => [], 'section' => [], 'article' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],

        // تأکید و نشانه‌گذاری متن
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        's' => [], 'del' => [], 'ins' => [], 'mark' => [],
        'small' => [], 'sub' => [], 'sup' => [], 'abbr' => ['title'],

        // فهرست‌ها
        'ul' => [], 'ol' => ['start', 'reversed', 'type'], 'li' => ['value'],
        'dl' => [], 'dt' => [], 'dd' => [],

        // نقل‌قول و کد
        'blockquote' => ['cite'], 'pre' => [], 'code' => [],
        'kbd' => [], 'samp' => [], 'var' => [],

        // پیوند و رسانه
        'a'          => ['href', 'target', 'rel', 'download'],
        'img'        => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'figure'     => [], 'figcaption' => [],
        'video'      => ['src', 'poster', 'width', 'height', 'controls', 'preload', 'muted', 'loop'],
        'audio'      => ['src', 'controls', 'preload', 'loop'],
        'source'     => ['src', 'type'],
        'track'      => ['src', 'kind', 'srclang', 'label'],

        // جدول
        'table' => [], 'caption' => [], 'colgroup' => ['span'], 'col' => ['span'],
        'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],

        // سایر
        'time' => ['datetime'],
    ];
}

/**
 * ویژگی‌هایی که روی همه عناصر مجازند
 *
 * ویژگی style عمداً مجاز نیست: هم می‌تواند برای فریب کاربر (تغییر
 * چیدمان و پوشاندن عناصر) استفاده شود و هم در مرورگرهای قدیمی راهی
 * برای اجرای کد بود.
 *
 * @return string[]
 */
function allowedGlobalAttributes(): array
{
    return ['class', 'id', 'title', 'dir', 'lang'];
}

/**
 * ویژگی‌هایی که مقدارشان یک آدرس است و باید اعتبارسنجی شوند
 *
 * @return string[]
 */
function urlAttributes(): array
{
    return ['href', 'src', 'cite', 'poster'];
}

/**
 * پاک‌سازی HTML ورودی کاربر
 *
 * کاربران دارای دسترسی نوشتن می‌توانند HTML محدود بنویسند، اما هر
 * عنصر یا ویژگی خارج از فهرست سفید حذف می‌شود.
 */
function sanitizeHtml(string $html): string
{
    $html = trim(str_replace("\0", '', $html));

    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        // بدون افزونه dom امکان پاک‌سازی مطمئن نیست؛ به متن ساده تبدیل می‌شود
        error_log('sanitizeHtml: DOM extension unavailable, falling back to plain text');

        return e(strip_tags($html));
    }

    $dom = new DOMDocument('1.0', 'UTF-8');

    // HTML کاربر تقریباً همیشه از نظر libxml ناقص است؛ هشدارها نباید
    // به گزارش خطای سرور یا خروجی راه پیدا کنند
    $previous = libxml_use_internal_errors(true);

    // اعلان encoding لازم است تا libxml ورودی را latin1 فرض نکند و
    // متن فارسی خراب نشود
    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8"?><div id="fardcms-sanitize-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return e(strip_tags($html));
    }

    // ریشه‌ی بسته‌بندی‌شده؛ اگر پیدا نشد چیزی برای پاک‌سازی نیست
    $root = null;

    foreach ($dom->childNodes as $node) {
        if ($node instanceof DOMElement && $node->getAttribute('id') === 'fardcms-sanitize-root') {
            $root = $node;
            break;
        }
    }

    if ($root === null) {
        return e(strip_tags($html));
    }

    sanitizeNode($root, allowedHtmlElements(), allowedGlobalAttributes());

    // خروجی: فقط محتوای داخل ریشه، بدون خود عنصر بسته‌بندی
    $output = '';

    foreach ($root->childNodes as $child) {
        $output .= $dom->saveHTML($child);
    }

    return trim($output);
}

/**
 * پیمایش بازگشتی و پاک‌سازی یک گره و فرزندانش
 *
 * @param array<string, string[]> $allowedElements
 * @param string[]                $globalAttributes
 */
function sanitizeNode(DOMNode $node, array $allowedElements, array $globalAttributes): void
{
    // پیمایش روی کپی فهرست فرزندان، چون در حین کار تغییر می‌کند
    foreach (iterator_to_array($node->childNodes) as $child) {
        // توضیحات HTML حذف می‌شوند؛ در حمله‌های mXSS به‌کار می‌روند
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
            $child->parentNode?->removeChild($child);
            continue;
        }

        if ($child instanceof DOMCdataSection) {
            // محتوای CDATA به متن ساده تبدیل می‌شود
            $child->parentNode?->replaceChild(
                $child->ownerDocument->createTextNode($child->nodeValue ?? ''),
                $child
            );
            continue;
        }

        if ($child instanceof DOMText) {
            continue;   // متن بی‌خطر است و در زمان serialize فرار داده می‌شود
        }

        if (!$child instanceof DOMElement) {
            $child->parentNode?->removeChild($child);
            continue;
        }

        $tag = strtolower($child->nodeName);

        // عنصر غیرمجاز حذف می‌شود، اما متن داخلش حفظ می‌شود مگر آنکه
        // خودش عنصری اجرایی باشد
        if (!array_key_exists($tag, $allowedElements)) {
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed',
                                'form', 'base', 'meta', 'link', 'template',
                                'noscript', 'svg', 'math'], true)) {
                // این عناصر با تمام محتوایشان حذف می‌شوند
                $child->parentNode?->removeChild($child);
                continue;
            }

            unwrapElement($child, $allowedElements, $globalAttributes);
            continue;
        }

        cleanAttributes($child, $tag, $allowedElements[$tag], $globalAttributes);

        sanitizeNode($child, $allowedElements, $globalAttributes);
    }
}

/**
 * حذف یک عنصر و جایگزینی آن با فرزندانش
 *
 * @param array<string, string[]> $allowedElements
 * @param string[]                $globalAttributes
 */
function unwrapElement(DOMElement $element, array $allowedElements, array $globalAttributes): void
{
    $parent = $element->parentNode;

    if ($parent === null) {
        return;
    }

    // ابتدا فرزندان پاک‌سازی می‌شوند، سپس به سطح بالا منتقل می‌شوند
    sanitizeNode($element, $allowedElements, $globalAttributes);

    while ($element->firstChild !== null) {
        $parent->insertBefore($element->firstChild, $element);
    }

    $parent->removeChild($element);
}

/**
 * حذف ویژگی‌های غیرمجاز یک عنصر
 *
 * @param string[] $elementAttributes
 * @param string[] $globalAttributes
 */
function cleanAttributes(
    DOMElement $element,
    string $tag,
    array $elementAttributes,
    array $globalAttributes
): void {
    $allowed = array_merge($elementAttributes, $globalAttributes);
    $urlAttrs = urlAttributes();

    foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
        $name = strtolower($attribute->nodeName);

        // هر ویژگی خارج از فهرست سفید حذف می‌شود؛ این یعنی تمام
        // رویدادها (on*)، srcset، formaction، autofocus و مانند آن‌ها
        if (!in_array($name, $allowed, true)) {
            $element->removeAttribute($attribute->nodeName);
            continue;
        }

        if (in_array($name, $urlAttrs, true)) {
            $safeUrl = sanitizeUrl((string) $attribute->nodeValue);

            if ($safeUrl === null) {
                $element->removeAttribute($attribute->nodeName);
            } else {
                $element->setAttribute($name, $safeUrl);
            }
        }
    }

    // مقدار target فقط _blank پذیرفته می‌شود و همراه آن rel امنیتی لازم است
    if ($element->hasAttribute('target')) {
        if (strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        } else {
            $element->removeAttribute('target');
        }
    }

    // تصاویر بهتر است تنبل بارگذاری شوند و همیشه متن جایگزین داشته باشند
    if ($tag === 'img') {
        if (!$element->hasAttribute('alt')) {
            $element->setAttribute('alt', '');
        }

        if (!$element->hasAttribute('loading')) {
            $element->setAttribute('loading', 'lazy');
        }
    }
}

/**
 * اعتبارسنجی یک آدرس
 *
 * @return string|null آدرس امن، یا null اگر مجاز نباشد
 */
function sanitizeUrl(string $url): ?string
{
    // ابتدا موجودیت‌های HTML و کاراکترهای کنترلی رمزگشایی و حذف می‌شوند؛
    // مهاجم با `java&#115;cript:` یا `jav&#x09;ascript:` فیلترهای ساده
    // را رد می‌کند در حالی که مرورگر آن را اجرا می‌کند
    $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = preg_replace('/[\x00-\x20\x7F\xA0]|&#x?0*(9|10|13|A|D);?/i', '', $decoded) ?? '';
    $decoded = trim($decoded);

    if ($decoded === '') {
        return null;
    }

    // آدرس نسبی، لنگر و مسیر ریشه‌نسبی مجاز است
    if (preg_match('#^(/|\./|\.\./|\#|\?)#', $decoded)) {
        return $url;
    }

    // پروتکل مشخص؟ فقط فهرست سفید
    if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $decoded, $matches)) {
        $scheme = strtolower($matches[1]);

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
    }

    // بدون پروتکل و بدون اسلش: آدرس نسبی مانند "page/name"
    return $url;
}

/**
 * پاک‌سازی متن‌های کوتاه با HTML بسیار محدود (مثل متن پاورقی)
 */
function sanitizeInlineHtml(string $html): string
{
    $allowed = [
        'a' => ['href', 'target', 'rel'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'span' => [], 'br' => [], 'small' => [], 'time' => ['datetime'],
    ];

    $html = trim(str_replace("\0", '', $html));

    if ($html === '' || !class_exists('DOMDocument')) {
        return e(strip_tags($html));
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);

    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8"?><div id="fardcms-sanitize-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return e(strip_tags($html));
    }

    $root = null;

    foreach ($dom->childNodes as $node) {
        if ($node instanceof DOMElement && $node->getAttribute('id') === 'fardcms-sanitize-root') {
            $root = $node;
            break;
        }
    }

    if ($root === null) {
        return e(strip_tags($html));
    }

    sanitizeNode($root, $allowed, ['class', 'title', 'dir', 'lang']);

    $output = '';

    foreach ($root->childNodes as $child) {
        $output .= $dom->saveHTML($child);
    }

    return trim($output);
}
