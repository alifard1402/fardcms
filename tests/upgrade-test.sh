#!/bin/bash
# تست سازگاری به‌روزرسانی
#
# چرا لازم است: config.php متعلق به کاربر است و در به‌روزرسانی جایگزین
# نمی‌شود. اگر کد جدید به فایلی وابسته باشد که فقط از config.php صدا زده
# می‌شود، نصب‌های موجود با «خطای داخلی سرور» می‌شکنند — حتی وقتی نصب تازه
# بی‌عیب کار می‌کند.
#
# این تست کد فعلی را با config.php نسخه‌های قدیمی‌تر اجرا می‌کند.
#
# اجرا:  bash tests/upgrade-test.sh

cd "$(dirname "$0")/.." || exit 1
ROOT=$(pwd)
WORK=$(mktemp -d)
pass=0; fail=0

# نسخه‌هایی از config.php که کاربران ممکن است هنوز داشته باشند
OLD_REVS=$(git log --format=%H -- config.php | tail -5)

trap 'rm -rf "$WORK"' EXIT

for rev in $OLD_REVS; do
  short=$(git rev-parse --short "$rev")
  dir="$WORK/$short"
  mkdir -p "$dir"

  # کد امروز
  tar --exclude=.git --exclude=node_modules --exclude=tests \
      --exclude=config.php -cf - . | (cd "$dir" && tar xf -)

  # اما config.php آن نسخه قدیمی
  git show "$rev:config.php" > "$dir/config.php" 2>/dev/null || continue

  out=$(cd "$dir" && php -r '
    require "config.php";

    // ثابت‌ها و توابعی که کد جدید به آن‌ها تکیه دارد
    $needed = ["FARDCMS_VERSION"];
    foreach ($needed as $c) {
      if (!defined($c)) { fwrite(STDERR, "ثابت تعریف‌نشده: $c\n"); exit(1); }
    }

    foreach (["routeUrl", "assetUrl", "sanitizeHtml", "prettyUrls",
              "setPostStatus", "likeCondition", "pickAllowed"] as $fn) {
      if (!function_exists($fn)) { fwrite(STDERR, "تابع تعریف‌نشده: $fn\n"); exit(1); }
    }

    // مسیر واقعی پیشخوان که خطا می‌داد
    $_SESSION["user_id"] = 1;
    $_SESSION["user_role"] = "administrator";
    $_SESSION["user_name"] = "t";
    $_SESSION["user_email"] = "t@e.com";
    getPostCounts("post");
    getPosts(["type" => "post", "status" => "any", "per_page" => 3]);
    echo FARDCMS_VERSION;
  ' 2>&1)

  if [ $? -eq 0 ]; then
    printf "  PASS  config.php از %s → کد امروز اجرا شد (نسخه %s)\n" "$short" "$out"
    pass=$((pass+1))
  else
    printf "  FAIL  config.php از %s\n        %s\n" "$short" "$(echo "$out" | head -2 | tr '\n' ' ')"
    fail=$((fail+1))
  fi
done

echo
echo "════ PASS: $pass  FAIL: $fail ════"

if [ $fail -gt 0 ]; then
  echo
  echo "هر فایل هسته‌ای که تازه اضافه می‌شود باید از یک فایلِ همیشه‌بارگذاری‌شده"
  echo "(مثل includes/db.php) صدا زده شود، نه از config.php."
fi

exit $([ $fail -eq 0 ] && echo 0 || echo 1)
