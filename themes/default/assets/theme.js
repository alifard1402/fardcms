/**
 * جاوااسکریپت قالب پیش‌فرض
 *
 * بدون وابستگی بیرونی نوشته شده است. تمام قابلیت‌های اصلی سایت
 * (خواندن نوشته، ناوبری، جستجو) بدون جاوااسکریپت هم کار می‌کنند.
 */

(function () {
  'use strict';

  /* ─── پوسته تیره و روشن ─────────────────────────────────── */
  const THEME_KEY = 'fardcms_site_theme';

  const themeToggle = document.getElementById('theme-toggle');
  const iconDark = document.querySelector('.theme-icon-dark');
  const iconLight = document.querySelector('.theme-icon-light');

  /**
   * پوسته انتخابی کاربر یا پوسته سیستم
   */
  function currentTheme() {
    try {
      const saved = localStorage.getItem(THEME_KEY);

      if (saved === 'dark' || saved === 'light') {
        return saved;
      }
    } catch {
      // دسترسی به حافظه محلی ممکن نیست (مرورگر ناشناس یا مسدود)
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    // آیکون دکمه، پوسته‌ای را نشان می‌دهد که با کلیک فعال می‌شود
    if (iconDark && iconLight) {
      iconDark.style.display = theme === 'dark' ? 'none' : '';
      iconLight.style.display = theme === 'dark' ? '' : 'none';
    }
  }

  applyTheme(currentTheme());

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      const next = currentTheme() === 'dark' ? 'light' : 'dark';

      try {
        localStorage.setItem(THEME_KEY, next);
      } catch {
        // ذخیره نشد؛ انتخاب فقط برای همین صفحه اعمال می‌شود
      }

      applyTheme(next);
    });
  }

  /* ─── فهرست موبایل ──────────────────────────────────────── */
  const navToggle = document.getElementById('nav-toggle');
  const siteNav = document.getElementById('site-nav');

  if (navToggle && siteNav) {
    navToggle.addEventListener('click', function () {
      const isOpen = siteNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  /* ─── جستجوی سربرگ ──────────────────────────────────────── */
  const searchToggle = document.getElementById('search-toggle');
  const headerSearch = document.getElementById('header-search');
  const searchField = document.getElementById('search-field');

  if (searchToggle && headerSearch) {
    searchToggle.addEventListener('click', function () {
      const isOpen = headerSearch.classList.toggle('is-open');
      searchToggle.setAttribute('aria-expanded', String(isOpen));

      if (isOpen && searchField) {
        searchField.focus();
      }
    });

    // بستن با کلید Escape
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && headerSearch.classList.contains('is-open')) {
        headerSearch.classList.remove('is-open');
        searchToggle.setAttribute('aria-expanded', 'false');
        searchToggle.focus();
      }
    });
  }

  /* ─── فرم دیدگاه ────────────────────────────────────────── */
  const form = document.getElementById('comment-form');

  if (!form) {
    return;
  }

  const submitBtn = document.getElementById('comment-submit');
  const submitText = document.getElementById('comment-submit-text');
  const successBox = document.getElementById('comment-success');
  const successText = document.getElementById('comment-success-text');
  const errorBox = document.getElementById('comment-error');
  const errorText = document.getElementById('comment-error-text');
  const contentField = document.getElementById('comment-content');
  const counter = document.getElementById('comment-counter');
  const parentField = document.getElementById('parent-id');
  const replyNotice = document.getElementById('reply-notice');
  const replyAuthor = document.getElementById('reply-author');
  const cancelReply = document.getElementById('cancel-reply');

  /** تبدیل ارقام لاتین به فارسی برای شمارنده */
  function toPersianDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  }

  /* شمارنده کاراکتر */
  if (contentField && counter) {
    const updateCounter = () => {
      counter.textContent = toPersianDigits(contentField.value.length.toLocaleString('en-US'));
    };

    contentField.addEventListener('input', updateCounter);
    updateCounter();
  }

  /* ─── پاسخ به یک دیدگاه ─────────────────────────────────── */
  document.querySelectorAll('[data-reply-to]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!parentField) return;

      parentField.value = button.getAttribute('data-reply-to') || '';

      if (replyNotice && replyAuthor) {
        replyAuthor.textContent = button.getAttribute('data-reply-author') || '';
        replyNotice.style.display = '';
      }

      // فرم به دید کاربر آورده می‌شود و نشانگر داخل متن قرار می‌گیرد
      document.getElementById('comment-form-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      contentField?.focus();
    });
  });

  if (cancelReply && parentField && replyNotice) {
    cancelReply.addEventListener('click', function () {
      parentField.value = '';
      replyNotice.style.display = 'none';
    });
  }

  /* ─── ارسال فرم ─────────────────────────────────────────── */
  function showError(message) {
    if (errorBox && errorText) {
      errorText.textContent = message;
      errorBox.style.display = '';
      errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function hideMessages() {
    if (errorBox) errorBox.style.display = 'none';
    if (successBox) successBox.style.display = 'none';
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    hideMessages();

    const data = Object.fromEntries(new FormData(form).entries());

    // اعتبارسنجی سمت کاربر پیش از ارسال
    if (!data.content || data.content.trim().length < 3) {
      showError('متن دیدگاه باید حداقل ۳ کاراکتر باشد.');
      contentField?.focus();
      return;
    }

    // فیلدهای مهمان فقط وقتی وجود دارند که کاربر وارد نشده باشد
    if (Object.prototype.hasOwnProperty.call(data, 'author_name')) {
      if (!data.author_name || data.author_name.trim().length < 2) {
        showError('نام باید حداقل ۲ کاراکتر باشد.');
        return;
      }

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.author_email || '')) {
        showError('ایمیل معتبر وارد کنید.');
        return;
      }
    }

    if (submitBtn) submitBtn.disabled = true;
    if (submitText) submitText.textContent = 'در حال ارسال…';

    try {
      // آدرس از data-action خوانده می‌شود؛ مسیر نسبی روی نشانی‌های
      // تودرتو مانند /blog/slug به مسیر اشتباه حل می‌شد
      const endpoint = form.dataset.action;

      if (!endpoint) {
        showError('آدرس ارسال دیدگاه پیدا نشد. صفحه را دوباره بارگذاری کنید.');
        return;
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          post_id: Number(data.post_id),
          parent_id: data.parent_id ? Number(data.parent_id) : null,
          author_name: data.author_name,
          author_email: data.author_email,
          content: data.content,
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.success === false) {
        showError(payload.message || 'ارسال دیدگاه ممکن نشد. لطفاً دوباره تلاش کنید.');
        return;
      }

      // دیدگاه تأییدشده بلافاصله در صفحه دیده می‌شود؛ برای همین صفحه
      // بازخوانی می‌شود. دیدگاه در انتظار تأیید فقط پیام می‌گیرد.
      if (successBox && successText) {
        successText.textContent = payload.message || 'دیدگاه شما ثبت شد.';
        successBox.style.display = '';
        successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      form.reset();

      if (parentField) parentField.value = '';
      if (replyNotice) replyNotice.style.display = 'none';
      if (counter) counter.textContent = '۰';

      if (payload.data && payload.data.status === 'approved') {
        setTimeout(() => window.location.reload(), 1200);
      }
    } catch {
      showError('خطا در ارتباط با سرور. اتصال اینترنت خود را بررسی کنید.');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
      if (submitText) submitText.textContent = 'ارسال دیدگاه';
    }
  });
})();
