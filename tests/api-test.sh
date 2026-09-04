#!/bin/bash
B=http://127.0.0.1:8765
J=/tmp/fardcms-test-cookies.txt
rm -f $J
pass=0; fail=0

# پایگاه داده برای هر اجرا از صفر ساخته می‌شود تا تست تکرارپذیر بماند
cd /home/user/fardcms
php -r 'require "config.php"; require INCLUDES_PATH."/schema.php"; dropSchema(Database::getConnection());'
rm -f storage/installed.lock
php setup.php --email=admin@fardcms.local --username=admin --password='Admin@1234' --name='مدیر سایت' > /dev/null 2>&1 || { echo "SETUP FAILED"; exit 1; }
chk() { # name expected_substring actual
  if echo "$3" | grep -q "$2"; then echo "  PASS  $1"; pass=$((pass+1));
  else echo "  FAIL  $1"; echo "        got: $(echo $3 | head -c 300)"; fail=$((fail+1)); fi
}

echo "=== unauthenticated ==="
r=$(curl -s -c $J "$B/api/auth/me.php")
chk "me: not authenticated" '"authenticated":false' "$r"

r=$(curl -s -b $J "$B/api/posts/index.php")
chk "posts list blocked when logged out" 'وارد شوید' "$r"

r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -d '{"login":"admin","password":"wrong"}' "$B/api/auth/login.php")
chk "login rejects bad password" 'اشتباه است' "$r"

r=$(curl -s -b $J "$B/api/posts/index.php" -X POST)
chk "GET-only endpoint rejects POST" 'روش درخواست' "$r"

echo "=== login ==="
r=$(curl -s -c $J -b $J -X POST -H 'Content-Type: application/json' -d '{"login":"admin","password":"Admin@1234"}' "$B/api/auth/login.php")
chk "login succeeds" '"success":true' "$r"
CSRF=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["csrf_token"])')
chk "csrf token returned" '.' "$CSRF"
chk "redirect to admin" 'admin' "$r"

r=$(curl -s -b $J "$B/api/auth/me.php")
chk "me: authenticated" '"authenticated":true' "$r"
chk "me: administrator role" 'administrator' "$r"
chk "me: caps include manage_settings" 'manage_settings' "$r"

echo "=== CSRF enforcement ==="
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -d '{"title":"x"}' "$B/api/posts/save.php")
chk "save without CSRF rejected" 'توکن امنیتی' "$r"

echo "=== posts CRUD ==="
r=$(curl -s -b $J "$B/api/posts/index.php?type=post")
chk "posts list works" '"posts"' "$r"
chk "posts counts present" '"counts"' "$r"

r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"title":"نوشته آزمایشی من","content":"<p>متن تست</p><script>alert(1)</script>","status":"publish","categories":[2],"tags":["تست","آزمون"],"comment_status":true}' \
  "$B/api/posts/save.php")
chk "create post" '"success":true' "$r"
PID=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["post"]["id"])')
chk "post id returned" '[0-9]' "$PID"
echo "$r" | grep -q 'alert(1)' && { echo "  FAIL  XSS not stripped"; fail=$((fail+1)); } || { echo "  PASS  XSS script stripped from content"; pass=$((pass+1)); }
chk "slug generated from persian title" 'نوشته-آزمایشی-من' "$r"
chk "tags created" 'آزمون' "$r"

r=$(curl -s -b $J "$B/api/posts/show.php?id=$PID")
chk "show post" '"success":true' "$r"

r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d "{\"id\":$PID,\"title\":\"نوشته ویرایش‌شده\",\"content\":\"<p>ویرایش</p>\",\"status\":\"publish\"}" "$B/api/posts/save.php")
chk "update post" 'نوشته ویرایش‌شده' "$r"

r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d "{\"id\":$PID,\"action\":\"trash\"}" "$B/api/posts/delete.php")
chk "trash post" 'زباله‌دان' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d "{\"id\":$PID,\"action\":\"restore\"}" "$B/api/posts/delete.php")
chk "restore post" 'بازگردانی' "$r"

echo "=== search (placeholder reuse regression) ==="
r=$(curl -s -b $J "$B/api/posts/index.php?type=post&status=any&search=%D9%81%D8%B1%D8%AF")
chk "posts search works" '"success":true' "$r"
r=$(curl -s -b $J "$B/api/users/index.php?search=admin")
chk "users search works" '"success":true' "$r"
r=$(curl -s -b $J "$B/api/comments/index.php?search=x")
chk "comments search works" '"success":true' "$r"
r=$(curl -s -b $J "$B/api/media/index.php?search=x")
chk "media search works" '"success":true' "$r"

echo "=== terms ==="
r=$(curl -s -b $J "$B/api/terms/index.php?taxonomy=category")
chk "terms list" '"tree"' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d '{"name":"دسته تست","taxonomy":"category","parent_id":2}' "$B/api/terms/save.php")
chk "create category" '"success":true' "$r"
TID=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["term"]["id"])')
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d "{\"id\":$TID,\"name\":\"دسته تست\",\"taxonomy\":\"category\",\"parent_id\":$TID}" "$B/api/terms/save.php")
chk "self-parent cycle rejected" 'زیرمجموعه خودش' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d "{\"id\":$TID}" "$B/api/terms/delete.php")
chk "delete category" 'حذف شد' "$r"

echo "=== comments ==="
r=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"post_id":1,"author_name":"مهمان تست","author_email":"g@example.com","content":"دیدگاه آزمایشی از بازدیدکننده"}' "$B/api/comments/create.php")
chk "guest comment accepted" '"success":true' "$r"
r=$(curl -s -b $J "$B/api/comments/index.php?status=pending")
chk "pending comments listed" 'مهمان تست' "$r"
CID=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["comments"][0]["id"])')
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d "{\"id\":$CID,\"action\":\"approve\"}" "$B/api/comments/moderate.php")
chk "approve comment" 'به‌روزرسانی شد' "$r"

echo "=== آپلود: بررسی نوع واقعی فایل ==="
UP=$(mktemp -d)
php -r '$im=imagecreatetruecolor(60,40); imagefill($im,0,0,imagecolorallocate($im,120,80,255)); imagepng($im,"'$UP'/ok.png");'
printf '<?php system($_GET["c"]); ?>' > $UP/evil.png
printf '\xff\xd8\xff\xe0<?php system($_GET["c"]); ?>' > $UP/poly.jpg
printf 'GIF89a<?php system($_GET["c"]); ?>' > $UP/poly.gif
printf '<?php echo 1; ?>' > $UP/shell.php
printf '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="9" height="9"/></svg>' > $UP/s.svg

r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/ok.png" "$B/api/media/upload.php")
chk "تصویر معتبر پذیرفته می‌شود" '"success":true' "$r"
r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/evil.png" "$B/api/media/upload.php")
chk "php با پسوند png رد می‌شود" '"success":false' "$r"
r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/poly.jpg" "$B/api/media/upload.php")
chk "polyglot jpeg رد می‌شود" '"success":false' "$r"
r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/poly.gif" "$B/api/media/upload.php")
chk "polyglot gif رد می‌شود" '"success":false' "$r"
r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/shell.php" "$B/api/media/upload.php")
chk "پسوند php رد می‌شود" '"success":false' "$r"
r=$(curl -s -b $J -H "X-CSRF-Token: $CSRF" -F "file[]=@$UP/s.svg" "$B/api/media/upload.php")
chk "svg پذیرفته می‌شود" '"success":true' "$r"
SVGURL=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["media"][0]["url"])' 2>/dev/null)
if [ -n "$SVGURL" ]; then
  body=$(curl -s "$SVGURL")
  echo "$body" | grep -q "<script" && { echo "  FAIL  اسکریپت svg حذف نشد"; fail=$((fail+1)); } \
    || { echo "  PASS  اسکریپت svg حذف شد"; pass=$((pass+1)); }
fi
rm -rf $UP

echo "=== users ==="
r=$(curl -s -b $J "$B/api/users/index.php")
chk "users list" '"users"' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"name":"نویسنده تست","email":"author@test.local","username":"testauthor","password":"Author@123","role":"author"}' "$B/api/users/save.php")
chk "create user" '"success":true' "$r"
UID2=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["user"]["id"])')
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d '{"id":1,"role":"author"}' "$B/api/users/save.php")
chk "cannot demote self" 'نقش خودتان' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -d '{"id":1}' "$B/api/users/delete.php")
chk "cannot delete self" 'حساب خودتان' "$r"

echo "=== settings ==="
r=$(curl -s -b $J "$B/api/settings/index.php")
chk "settings read" '"settings"' "$r"
r=$(curl -s -b $J -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"site_title":"سایت تست","posts_per_page":500,"default_role":"administrator","google_analytics":"<script>bad</script>"}' "$B/api/settings/index.php")
chk "settings saved" 'ذخیره شد' "$r"
r=$(curl -s -b $J "$B/api/settings/index.php")
chk "posts_per_page clamped to 100" '"posts_per_page":100' "$r"
chk "default_role cannot be admin" '"default_role":"subscriber"' "$r"
chk "analytics script rejected" '"google_analytics":""' "$r"

echo "=== menus + dashboard ==="
r=$(curl -s -b $J "$B/api/menus/index.php")
chk "menus list" '"menus"' "$r"
r=$(curl -s -b $J "$B/api/dashboard/stats.php")
chk "dashboard stats" '"stats"' "$r"
chk "dashboard chart has 30 days" '"chart"' "$r"
python3 -c "
import json,sys
d=json.load(open('/dev/stdin'))
c=len(d['data']['chart'])
print('  PASS  chart length 30' if c==30 else f'  FAIL  chart length {c}')
" <<<"$r"

echo "=== privilege separation: author role ==="
JA=/tmp/fardcms-test-cookies-author.txt
rm -f $JA
r=$(curl -s -c $JA -X POST -H 'Content-Type: application/json' -d '{"login":"testauthor","password":"Author@123"}' "$B/api/auth/login.php")
chk "author login" '"success":true' "$r"
CSRF2=$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["csrf_token"])')
r=$(curl -s -b $JA "$B/api/users/index.php")
chk "author blocked from users" 'دسترسی' "$r"
r=$(curl -s -b $JA "$B/api/settings/index.php")
chk "author blocked from settings" 'دسترسی' "$r"
r=$(curl -s -b $JA -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF2" -d '{"id":1,"title":"سرقت محتوا","content":"x","status":"publish"}' "$B/api/posts/save.php")
chk "author cannot edit others' post" 'اجازه ویرایش' "$r"
r=$(curl -s -b $JA "$B/api/posts/index.php")
chk "author sees only own posts" '"total":0' "$r"

echo "=== logout ==="
r=$(curl -s -b $J -X POST -H "X-CSRF-Token: $CSRF" "$B/api/auth/logout.php")
chk "logout" 'خارج شدید' "$r"
r=$(curl -s -b $J "$B/api/auth/me.php")
chk "session cleared" '"authenticated":false' "$r"

echo ""
echo "════ PASS: $pass  FAIL: $fail ════"
exit $([ $fail -eq 0 ] && echo 0 || echo 1)
