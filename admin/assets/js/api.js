/**
 * لایه ارتباط با REST API
 *
 * توکن CSRF به‌صورت خودکار به تمام درخواست‌های تغییردهنده اضافه می‌شود.
 */

const BASE = '../api';

/** توکن CSRF نشست فعلی */
let csrfToken = '';

export function setCsrfToken(token) {
  csrfToken = token || '';
}

export function getCsrfToken() {
  return csrfToken;
}

/**
 * خطای برگشتی از API همراه با کد وضعیت HTTP
 */
export class ApiError extends Error {
  constructor(message, status, data = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }
}

/**
 * اجرای یک درخواست به API
 *
 * @param {string} path      مسیر نسبی، مثلاً 'posts/index.php'
 * @param {object} options   تنظیمات: method، body، params، isFormData
 * @returns {Promise<object>} بخش data از پاسخ
 */
async function request(path, { method = 'GET', body = null, params = null, isFormData = false } = {}) {
  const url = new URL(`${BASE}/${path}`, window.location.href);

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      // مقادیر خالی به کوئری اضافه نمی‌شوند تا آدرس تمیز بماند
      if (value !== null && value !== undefined && value !== '') {
        url.searchParams.set(key, value);
      }
    });
  }

  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

  if (!['GET', 'HEAD'].includes(method) && csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  if (body && !isFormData) {
    headers['Content-Type'] = 'application/json';
  }

  let response;

  try {
    response = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
    });
  } catch {
    throw new ApiError('خطا در ارتباط با سرور. اتصال اینترنت خود را بررسی کنید.', 0);
  }

  let payload = {};

  try {
    payload = await response.json();
  } catch {
    // پاسخ JSON نبود؛ پیام خطای عمومی بر اساس کد وضعیت ساخته می‌شود
    if (!response.ok) {
      throw new ApiError(`خطای سرور (${response.status})`, response.status);
    }
  }

  if (!response.ok || payload.success === false) {
    throw new ApiError(payload.message || `خطای سرور (${response.status})`, response.status, payload.data);
  }

  return payload.data || {};
}

const get = (path, params) => request(path, { params });
const post = (path, body) => request(path, { method: 'POST', body });

/**
 * تمام نقاط پایانی API به تفکیک بخش
 */
export const api = {
  auth: {
    me:       () => get('auth/me.php'),
    login:    (credentials) => post('auth/login.php', credentials),
    logout:   () => post('auth/logout.php'),
    register: (data) => post('auth/register.php', data),
    forgotPassword: (email) => post('auth/forgot-password.php', { email }),
    resetPassword:  (token, password) => post('auth/reset-password.php', { token, password }),
    verifyToken:    (token) => get('auth/verify-token.php', { token }),
  },

  posts: {
    list:   (params) => get('posts/index.php', params),
    show:   (id) => get('posts/show.php', { id }),
    save:   (data) => post('posts/save.php', data),
    remove: (id, action = 'trash') => post('posts/delete.php', { id, action }),
    bulk:   (ids, action) => post('posts/bulk.php', { ids, action }),
  },

  terms: {
    list:   (taxonomy, params) => get('terms/index.php', { taxonomy, ...params }),
    save:   (data) => post('terms/save.php', data),
    remove: (id) => post('terms/delete.php', { id }),
  },

  media: {
    list:   (params) => get('media/index.php', params),
    update: (data) => post('media/update.php', data),
    remove: (id) => post('media/delete.php', { id }),
    upload: (files) => {
      const form = new FormData();
      Array.from(files).forEach((file) => form.append('file[]', file));

      return request('media/upload.php', { method: 'POST', body: form, isFormData: true });
    },
  },

  comments: {
    list:     (params) => get('comments/index.php', params),
    moderate: (payload) => post('comments/moderate.php', payload),
  },

  users: {
    list:           (params) => get('users/index.php', params),
    save:           (data) => post('users/save.php', data),
    remove:         (id, reassignTo = 0) => post('users/delete.php', { id, reassign_to: reassignTo }),
    profile:        () => get('users/profile.php'),
    saveProfile:    (data) => post('users/profile.php', data),
    changePassword: (currentPassword, newPassword) =>
      post('users/change-password.php', { current_password: currentPassword, new_password: newPassword }),
  },

  settings: {
    get:  () => get('settings/index.php'),
    save: (data) => post('settings/index.php', data),
  },

  menus: {
    list:   () => get('menus/index.php'),
    save:   (data) => post('menus/save.php', data),
    remove: (id) => post('menus/delete.php', { id }),
  },

  dashboard: {
    stats:    () => get('dashboard/stats.php'),
    activity: (params) => get('dashboard/activity.php', params),
  },
};
