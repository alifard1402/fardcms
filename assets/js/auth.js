/**
 * توابع مشترک صفحه‌های احراز هویت
 *
 * این صفحه‌ها بیرون از پنل مدیریت قرار دارند و به لایه کامل API
 * نیازی ندارند؛ بنابراین یک لایه کوچک و مستقل استفاده می‌شود.
 */

const BASE = 'api';

/**
 * خطای برگشتی از سرور
 */
export class AuthError extends Error {
  constructor(message, status) {
    super(message);
    this.name = 'AuthError';
    this.status = status;
  }
}

async function handle(response) {
  let payload = {};

  try {
    payload = await response.json();
  } catch {
    throw new AuthError(`پاسخ نامعتبر از سرور (${response.status})`, response.status);
  }

  if (!response.ok || payload.success === false) {
    throw new AuthError(payload.message || `خطای سرور (${response.status})`, response.status);
  }

  return payload.data || {};
}

/** درخواست GET */
export async function apiGet(path, params = null) {
  const url = new URL(`${BASE}/${path}`, window.location.href);

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        url.searchParams.set(key, value);
      }
    });
  }

  try {
    return await handle(await fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }));
  } catch (error) {
    if (error instanceof AuthError) throw error;

    throw new AuthError('خطا در ارتباط با سرور. اتصال خود را بررسی کنید.', 0);
  }
}

/** درخواست POST */
export async function apiPost(path, body = {}) {
  try {
    return await handle(await fetch(`${BASE}/${path}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }));
  } catch (error) {
    if (error instanceof AuthError) throw error;

    throw new AuthError('خطا در ارتباط با سرور. اتصال خود را بررسی کنید.', 0);
  }
}

/**
 * سنجش قدرت رمز عبور
 *
 * @returns {{score: number, label: string, color: string, percent: number}}
 */
export function passwordStrength(password) {
  if (!password) {
    return { score: 0, label: '', color: 'transparent', percent: 0 };
  }

  let score = 0;

  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;

  const levels = [
    { label: 'بسیار ضعیف', color: '#f87171' },
    { label: 'ضعیف',       color: '#f87171' },
    { label: 'متوسط',      color: '#fbbf24' },
    { label: 'خوب',        color: '#60a5fa' },
    { label: 'قوی',        color: '#4ade80' },
    { label: 'بسیار قوی',  color: '#4ade80' },
  ];

  const level = levels[Math.min(score, levels.length - 1)];

  return {
    score,
    label: level.label,
    color: level.color,
    percent: Math.round((Math.min(score, 5) / 5) * 100),
  };
}

/**
 * بررسی اعتبار رمز عبور مطابق قواعد سرور
 *
 * @returns {string|null} پیام خطا یا null اگر معتبر باشد
 */
export function validatePassword(password) {
  if (password.length < 8) {
    return 'رمز عبور باید حداقل ۸ کاراکتر باشد';
  }

  if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
    return 'رمز عبور باید شامل حروف بزرگ، کوچک و عدد باشد';
  }

  return null;
}

/** بررسی سادهٔ قالب ایمیل */
export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}

/**
 * ساخت و اتصال برنامه Vue صفحه احراز هویت
 */
export function createAuthApp(options) {
  return Vue.createApp(options).mount('#app');
}
