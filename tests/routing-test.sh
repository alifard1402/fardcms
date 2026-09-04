#!/bin/bash
# تست مسیریابی در دو حالت نشانی: تمیز و پرسمانی
#
# حالت پرسمانی برای هاست‌هایی است که mod_rewrite ندارند یا .htaccess را
# نادیده می‌گیرند؛ بدون آن تمام برگه‌ها خطای ۴۰۴ می‌گیرند.
#
# اجرا:  bash tests/routing-test.sh

B=${FARDCMS_TEST_URL:-http://127.0.0.1:8765}
pass=0; fail=0

expect() { # آدرس  کد-انتظاری  برچسب
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$B$1")
  if [ "$code" = "$2" ]; then
    printf "  PASS  %s\n" "$3"; pass=$((pass+1))
  else
    printf "  FAIL  %s — انتظار %s، دریافت %s (%s)\n" "$3" "$2" "$code" "$1"; fail=$((fail+1))
  fi
}

contains() { # آدرس  رشته  برچسب
  if curl -s --max-time 15 "$B$1" | grep -q -- "$2"; then
    printf "  PASS  %s\n" "$3"; pass=$((pass+1))
  else
    printf "  FAIL  %s — «%s» در خروجی %s نبود\n" "$3" "$2" "$1"; fail=$((fail+1))
  fi
}

setmode() { # true|false
  php -r "require '$(cd "$(dirname "$0")/.." && pwd)/config.php'; setOption('pretty_urls', $1);"
}

echo "═══ حالت نشانی تمیز ═══"
setmode true
expect "/"                          200 "صفحه اصلی"
expect "/blog"                      200 "فهرست وبلاگ"
expect "/blog/welcome-to-fardcms"   200 "نوشته"
expect "/about"                     200 "برگه درباره ما"
expect "/contact"                   200 "برگه تماس با ما"
expect "/category/tutorials"        200 "آرشیو دسته"
expect "/tag/php"                   200 "آرشیو برچسب"
expect "/author/admin"              200 "آرشیو نویسنده"
expect "/feed"                      200 "خوراک RSS"
expect "/sitemap.xml"               200 "نقشه سایت"
expect "/robots.txt"                200 "robots.txt"
expect "/__fardcms_probe"           200 "کاوه تشخیص"
expect "/this-does-not-exist"       404 "صفحه ناموجود ۴۰۴ می‌دهد"
# میزبان از SITE_URL می‌آید و لازم نیست با آدرس آزمون یکی باشد،
# پس فقط شکل مسیر بررسی می‌شود
contains "/" '/contact"' "پیوندها به شکل تمیز ساخته می‌شوند"
if curl -s --max-time 15 "$B/" | grep -q 'route=contact'; then
  printf "  FAIL  در حالت تمیز نباید پیوند پرسمانی ساخته شود\n"; fail=$((fail+1))
else
  printf "  PASS  در حالت تمیز پیوند پرسمانی ساخته نمی‌شود\n"; pass=$((pass+1))
fi

echo
echo "═══ حالت نشانی پرسمانی (بدون mod_rewrite) ═══"
setmode false
expect "/index.php"                                   200 "صفحه اصلی"
expect "/index.php?route=blog"                        200 "فهرست وبلاگ"
expect "/index.php?route=blog/welcome-to-fardcms"     200 "نوشته"
expect "/index.php?route=about"                       200 "برگه درباره ما"
expect "/index.php?route=contact"                     200 "برگه تماس با ما"
expect "/index.php?route=category/tutorials"          200 "آرشیو دسته"
expect "/index.php?route=tag/php"                     200 "آرشیو برچسب"
expect "/index.php?route=author/admin"                200 "آرشیو نویسنده"
expect "/index.php?route=feed"                        200 "خوراک RSS"
expect "/index.php?route=sitemap.xml"                 200 "نقشه سایت"
expect "/index.php?route=__fardcms_probe"             200 "کاوه تشخیص"
expect "/index.php?route=this-does-not-exist"         404 "صفحه ناموجود ۴۰۴ می‌دهد"
contains "/index.php" 'route=contact' "پیوندها به شکل پرسمانی ساخته می‌شوند"
contains "/index.php" 'route=blog/welcome-to-fardcms' "اسلش در پیوند رمزگذاری نمی‌شود"

echo
echo "═══ بازگشت به حالت پیش‌فرض ═══"
setmode true
expect "/contact" 200 "برگه با نشانی تمیز دوباره کار می‌کند"

echo
echo "════ PASS: $pass  FAIL: $fail ════"
exit $([ $fail -eq 0 ] && echo 0 || echo 1)
