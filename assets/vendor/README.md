# کتابخانه‌های بیرونی

فایل‌های این پوشه همراه پروژه ارائه می‌شوند تا سایت به هیچ CDN بیرونی
وابسته نباشد. این انتخاب سه مزیت دارد:

- سایت روی شبکه داخلی و بدون اینترنت هم کار می‌کند
- قطعی یا کندی CDN روی پنل مدیریت اثری ندارد
- آدرس IP بازدیدکنندگان به سرور شخص ثالث فرستاده نمی‌شود

| فایل                                  | نسخه    | پروانه | منبع                              |
|---------------------------------------|---------|--------|-----------------------------------|
| `vue.global.prod.js`                  | ۳.۵.۱۳  | MIT    | https://github.com/vuejs/core     |
| `../fonts/Vazirmatn-Variable.woff2`   | ۳۳.۰.۳  | OFL    | https://github.com/rastikerdar/vazirmatn |

متن پروانه‌ها در فایل‌های `vue.LICENSE` و `../fonts/Vazirmatn.OFL.txt` قرار دارد.

قلم وزیرمتن به‌ویژه از این جهت محلی ارائه می‌شود که دسترسی به
Google Fonts در برخی کشورها از جمله ایران مسدود است.

## به‌روزرسانی نسخه

```sh
npm pack vue@<version>
tar xzf vue-<version>.tgz package/dist/vue.global.prod.js
cp package/dist/vue.global.prod.js assets/vendor/vue.global.prod.js
```
