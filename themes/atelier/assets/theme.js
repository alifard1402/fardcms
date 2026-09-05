/**
 * جاوااسکریپت قالب آتلیه
 *
 * بدون وابستگی بیرونی. هر چیزی که برای دیدن سایت لازم است — خواندن،
 * ناوبری، باز کردن تصویر در اندازه کامل — بدون جاوااسکریپت هم کار
 * می‌کند؛ لینک هر تصویر گالری مستقیم به فایل اصلی اشاره دارد.
 */

(function () {
  'use strict';

  /* ─── سربرگ چسبان ───────────────────────────────────────── */
  const header = document.getElementById('site-header');

  if (header) {
    // سربرگ روی تصویر بزرگ شفاف است؛ با کمی اسکرول زمینه می‌گیرد تا
    // متنش روی هر عکسی خوانا بماند.
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 24);

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ─── فهرست موبایل ──────────────────────────────────────── */
  const navToggle = document.getElementById('nav-toggle');
  const nav = document.getElementById('site-nav');

  if (navToggle && nav) {
    const backdrop = document.getElementById('nav-backdrop');

    const setNav = (open) => {
      nav.classList.toggle('is-open', open);
      navToggle.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('nav-open', open);
    };

    navToggle.addEventListener('click', function () {
      setNav(!nav.classList.contains('is-open'));
    });

    // بستن با انتخاب یک پیوند، لمس بیرون کشو، یا کلید Escape
    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) setNav(false);
    });

    backdrop?.addEventListener('click', () => setNav(false));

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        setNav(false);
        navToggle.focus();
      }
    });
  }

  /* ─── لایت‌باکس گالری ───────────────────────────────────── */
  const lightbox = document.getElementById('lightbox');
  const links = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));

  if (lightbox && links.length) {
    const image = document.getElementById('lightbox-image');
    const caption = document.getElementById('lightbox-caption');
    const prevBtn = lightbox.querySelector('[data-lightbox-prev]');
    const nextBtn = lightbox.querySelector('[data-lightbox-next]');

    let index = 0;
    let lastFocused = null;

    const show = (next) => {
      index = (next + links.length) % links.length;

      const link = links[index];

      image.src = link.getAttribute('href');
      image.alt = link.getAttribute('data-caption') || '';
      caption.textContent = link.getAttribute('data-caption') || '';

      // با یک تصویر، دکمه‌های پیمایش معنا ندارند
      const many = links.length > 1;
      prevBtn.hidden = !many;
      nextBtn.hidden = !many;
    };

    const open = (start) => {
      lastFocused = document.activeElement;
      show(start);
      lightbox.hidden = false;
      document.body.classList.add('lightbox-open');
      lightbox.querySelector('[data-lightbox-close]').focus();
    };

    const close = () => {
      lightbox.hidden = true;
      document.body.classList.remove('lightbox-open');
      image.src = '';

      // نشانگر به همان تصویری برمی‌گردد که کاربر از آن آمده بود
      if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
      }
    };

    links.forEach(function (link, position) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        open(position);
      });
    });

    lightbox.addEventListener('click', function (event) {
      if (event.target.closest('[data-lightbox-close]') || event.target === lightbox) {
        close();
      } else if (event.target.closest('[data-lightbox-prev]')) {
        show(index - 1);
      } else if (event.target.closest('[data-lightbox-next]')) {
        show(index + 1);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (lightbox.hidden) return;

      if (event.key === 'Escape') close();
      // در راست‌به‌چپ، کلید «چپ» یعنی جلو رفتن
      if (event.key === 'ArrowLeft') show(index + 1);
      if (event.key === 'ArrowRight') show(index - 1);
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

  if (contentField && counter) {
    const updateCounter = () => {
      counter.textContent = toPersianDigits(contentField.value.length.toLocaleString('en-US'));
    };

    contentField.addEventListener('input', updateCounter);
    updateCounter();
  }

  document.querySelectorAll('[data-reply-to]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!parentField) return;

      parentField.value = button.getAttribute('data-reply-to') || '';

      if (replyNotice && replyAuthor) {
        replyAuthor.textContent = button.getAttribute('data-reply-author') || '';
        replyNotice.style.display = '';
      }

      document.getElementById('comment-form-wrap')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      contentField?.focus();
    });
  });

  if (cancelReply && parentField && replyNotice) {
    cancelReply.addEventListener('click', function () {
      parentField.value = '';
      replyNotice.style.display = 'none';
    });
  }

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

      if (successBox && successText) {
        successText.textContent = payload.message || 'دیدگاه شما ثبت شد.';
        successBox.style.display = '';
        successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      form.reset();

      if (parentField) parentField.value = '';
      if (replyNotice) replyNotice.style.display = 'none';
      if (counter) counter.textContent = '۰';

      // دیدگاه تأییدشده بلافاصله باید دیده شود؛ در انتظار تأیید فقط پیام
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
