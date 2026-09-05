#!/bin/bash
#
# تست سامانه قالب و افزونه
#
#   bash tests/extensions-test.sh
#
# نصب از زیپ کدِ اجراشدنی روی سرور می‌نشاند؛ بیشتر این تست صرف سنجیدن
# همان چیزی است که باید رد شود، نه چیزی که باید کار کند.
#
# پیش‌نیاز: سرور توسعه روی 127.0.0.1:8765 و نصب انجام‌شده (api-test.sh)

B=http://127.0.0.1:8765
J=/tmp/fardcms-ext-cookies.txt
W=/tmp/fardcms-ext-work
pass=0; fail=0

cd /home/user/fardcms || exit 1

chk() { # نام | متن مورد انتظار | خروجی واقعی
  if echo "$3" | grep -q "$2"; then echo "  PASS  $1"; pass=$((pass+1));
  else echo "  FAIL  $1"; echo "        got: $(echo "$3" | head -c 300)"; fail=$((fail+1)); fi
}

nchk() { # نام | متنی که نباید باشد | خروجی واقعی
  if echo "$3" | grep -q "$2"; then echo "  FAIL  $1"; echo "        got: $(echo "$3" | head -c 300)"; fail=$((fail+1));
  else echo "  PASS  $1"; pass=$((pass+1)); fi
}

rm -rf $W && mkdir -p $W
rm -f $J

CSRF=$(curl -s -c $J -X POST -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"Admin@1234"}' "$B/api/auth/login.php" \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["csrf_token"])')

install_zip() { # نوع | مسیر زیپ
  curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "kind=$1" -F "package=@$2" \
    "$B/api/extensions/install.php"
}

act() { # نوع | نامک | عمل
  curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
    -d "{\"kind\":\"$1\",\"slug\":\"$2\",\"action\":\"$3\"}" "$B/api/extensions/action.php"
}

echo "=== ساخت بسته‌های آزمایشی ==="
python3 - "$W" <<'PY'
import sys, zipfile
w = sys.argv[1]

# قالب سالم
with zipfile.ZipFile(f'{w}/ok-theme.zip', 'w') as z:
    z.writestr('zzz-test-theme/index.php', '<?php echo "ZZZ_TEST_THEME";')
    z.writestr('zzz-test-theme/theme.json', '{"name":"قالب آزمایشی","version":"1.0.0"}')
    z.writestr('zzz-test-theme/assets/style.css', 'body{}')

# افزونه سالم که روی یک صافی می‌نشیند
with zipfile.ZipFile(f'{w}/ok-plugin.zip', 'w') as z:
    z.writestr('zzz-test-plugin/plugin.json',
               '{"name":"افزونه آزمایشی","version":"1.0.0","main":"zzz-test-plugin.php"}')
    z.writestr('zzz-test-plugin/zzz-test-plugin.php',
               '<?php addFilter("zzz_test", fn($v) => $v . "_FROM_PLUGIN");')

# بسته‌های مخرب یا خراب
with zipfile.ZipFile(f'{w}/zipslip.zip', 'w') as z:
    z.writestr('x/index.php', '<?php')
    z.writestr('x/../../../evil-zzz.php', '<?php echo "pwned";')

with zipfile.ZipFile(f'{w}/absolute.zip', 'w') as z:
    z.writestr('/etc/evil-zzz.php', '<?php')

with zipfile.ZipFile(f'{w}/badext.zip', 'w') as z:
    z.writestr('y/index.php', '<?php')
    z.writestr('y/shell.phar', 'x')

with zipfile.ZipFile(f'{w}/tworoots.zip', 'w') as z:
    z.writestr('a/index.php', '<?php')
    z.writestr('b/index.php', '<?php')

with zipfile.ZipFile(f'{w}/notheme.zip', 'w') as z:
    z.writestr('empty-zzz/readme.txt', 'hi')

with zipfile.ZipFile(f'{w}/badname.zip', 'w') as z:
    z.writestr('نام فارسی/index.php', '<?php')

# فایل‌های پنهان نباید استخراج شوند
with zipfile.ZipFile(f'{w}/hidden.zip', 'w') as z:
    z.writestr('zzz-hidden-theme/index.php', '<?php')
    z.writestr('zzz-hidden-theme/.htaccess', 'Require all granted')

with open(f'{w}/notzip.zip', 'w') as f:
    f.write('این فایل زیپ نیست')
PY
echo "  ساخته شد"

echo "=== بسته‌های نامعتبر باید رد شوند ==="
nchk "zip-slip رد می‌شود" '"success":true' "$(install_zip theme $W/zipslip.zip)"
chk  "پیام zip-slip روشن است" 'مسیر نامعتبر' "$(install_zip theme $W/zipslip.zip)"
nchk "مسیر مطلق رد می‌شود" '"success":true' "$(install_zip theme $W/absolute.zip)"
chk  "پسوند خطرناک رد می‌شود" 'پسوند غیرمجاز' "$(install_zip theme $W/badext.zip)"
chk  "دو پوشه ریشه رد می‌شود" 'یک پوشه اصلی' "$(install_zip theme $W/tworoots.zip)"
chk  "بسته بدون index.php قالب نیست" 'قالب نیست' "$(install_zip theme $W/notheme.zip)"
chk  "نام غیرلاتین رد می‌شود" 'حروف انگلیسی' "$(install_zip theme $W/badname.zip)"
chk  "فایل غیرزیپ رد می‌شود" 'زیپ' "$(install_zip theme $W/notzip.zip)"

[ -f /home/user/evil-zzz.php ] || [ -f /evil-zzz.php ] || [ -f themes/evil-zzz.php ]
if [ $? -eq 0 ]; then
  echo "  FAIL  هیچ فایلی بیرون از مقصد نوشته نشد"; fail=$((fail+1))
else
  echo "  PASS  هیچ فایلی بیرون از مقصد نوشته نشد"; pass=$((pass+1))
fi

echo "=== نصب و چرخه عمر قالب ==="
chk "قالب سالم نصب می‌شود" 'نصب شد' "$(install_zip theme $W/ok-theme.zip)"
chk "قالب در فهرست می‌آید" 'zzz-test-theme' "$(curl -s -b $J "$B/api/extensions/index.php")"
chk "نصب دوباره بدون تأیید رد می‌شود" 'قبلاً نصب شده' "$(install_zip theme $W/ok-theme.zip)"

chk "فایل پنهان استخراج نمی‌شود" 'نصب شد' "$(install_zip theme $W/hidden.zip)"
if [ -f themes/zzz-hidden-theme/.htaccess ]; then
  echo "  FAIL  htaccess داخل بسته نادیده گرفته می‌شود"; fail=$((fail+1))
else
  echo "  PASS  htaccess داخل بسته نادیده گرفته می‌شود"; pass=$((pass+1))
fi

chk "قالب فعال می‌شود" 'قالب فعال شد' "$(act theme zzz-test-theme activate)"
chk "سایت با قالب تازه رندر می‌شود" 'ZZZ_TEST_THEME' "$(curl -s "$B/")"
chk "قالب فعال حذف نمی‌شود" 'اول قالب دیگری' "$(act theme zzz-test-theme delete)"
chk "بازگشت به قالب پیش‌فرض" 'قالب فعال شد' "$(act theme default activate)"
chk "قالب حذف می‌شود" 'حذف شد' "$(act theme zzz-test-theme delete)"
act theme zzz-hidden-theme delete > /dev/null
nchk "پس از حذف در فهرست نیست" 'zzz-test-theme' "$(curl -s -b $J "$B/api/extensions/index.php")"

echo "=== نصب و چرخه عمر افزونه ==="
chk "افزونه سالم نصب می‌شود" 'نصب شد' "$(install_zip plugin $W/ok-plugin.zip)"
chk "افزونه غیرفعال است" '"slug":"zzz-test-plugin","active":false' \
    "$(curl -s -b $J "$B/api/extensions/index.php" | python3 -c 'import sys,json;print(json.dumps([{"slug":p["slug"],"active":p["active"]} for p in json.load(sys.stdin)["data"]["plugins"]],ensure_ascii=False).replace(" ",""))')"

# صافی افزونه فقط پس از فعال شدن باید اثر کند
before=$(php -r 'require "config.php"; loadPlugins(); echo applyFilters("zzz_test", "BASE");')
chk "صافی افزونه پیش از فعال‌سازی بی‌اثر است" '^BASE$' "$before"

chk "افزونه فعال می‌شود" 'فعال شد' "$(act plugin zzz-test-plugin activate)"
after=$(php -r 'require "config.php"; loadPlugins(); echo applyFilters("zzz_test", "BASE");')
chk "صافی افزونه پس از فعال‌سازی کار می‌کند" 'BASE_FROM_PLUGIN' "$after"

chk "افزونه فعال حذف نمی‌شود" 'اول غیرفعالش کنید' "$(act plugin zzz-test-plugin delete)"
chk "افزونه غیرفعال می‌شود" 'غیرفعال شد' "$(act plugin zzz-test-plugin deactivate)"
gone=$(php -r 'require "config.php"; loadPlugins(); echo applyFilters("zzz_test", "BASE");')
chk "صافی پس از غیرفعال شدن برمی‌گردد" '^BASE$' "$gone"
chk "افزونه حذف می‌شود" 'حذف شد' "$(act plugin zzz-test-plugin delete)"

echo "=== دسترسی ==="
rm -f /tmp/fardcms-ext-author.txt
CSRF_A=$(curl -s -c /tmp/fardcms-ext-author.txt -X POST -H 'Content-Type: application/json' \
  -d '{"login":"testauthor","password":"Author@123"}' "$B/api/auth/login.php" \
  | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d.get("data",{}).get("csrf_token",""))')

if [ -n "$CSRF_A" ]; then
  r=$(curl -s -b /tmp/fardcms-ext-author.txt "$B/api/extensions/index.php")
  chk "نویسنده به فهرست قالب‌ها دسترسی ندارد" 'دسترسی' "$r"

  r=$(curl -s -b /tmp/fardcms-ext-author.txt -H "X-CSRF-Token: $CSRF_A" \
      -F "kind=theme" -F "package=@$W/ok-theme.zip" "$B/api/extensions/install.php")
  chk "نویسنده نمی‌تواند نصب کند" 'دسترسی' "$r"
else
  echo "  (کاربر نویسنده وجود ندارد؛ آزمون دسترسی رد شد)"
fi

echo "=== محافظت از فایل‌ها ==="
chk "کد قالب مستقیم اجرا نمی‌شود" '403' \
    "$(curl -s -o /dev/null -w '%{http_code}' "$B/themes/default/index.php")"
chk "کد افزونه مستقیم اجرا نمی‌شود" '403' \
    "$(curl -s -o /dev/null -w '%{http_code}' "$B/plugins/gallery/gallery.php")"
chk "فایل ظاهری افزونه در دسترس است" '200' \
    "$(curl -s -o /dev/null -w '%{http_code}' "$B/plugins/gallery/assets/admin.js")"

rm -rf $W

echo ""
echo "════ PASS: $pass  FAIL: $fail ════"
exit $([ $fail -gt 0 ] && echo 1 || echo 0)
