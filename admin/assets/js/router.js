/**
 * مسیریاب سبک بر پایه hash
 *
 * برای پنلی با این تعداد صفحه، یک مسیریاب کوچک از افزودن
 * vue-router و پیچیدگی آن ساده‌تر و سبک‌تر است.
 */

const { reactive } = Vue;

/** وضعیت مسیر فعلی */
export const route = reactive({
  path: '/',
  params: {},
  query: {},
});

/**
 * تعریف مسیرها
 *
 * هر الگو می‌تواند پارامتر پویا با پیشوند «:» داشته باشد.
 */
const routes = [
  { pattern: '/',                view: 'dashboard',  title: 'پیشخوان',        cap: 'edit_posts' },
  { pattern: '/posts',           view: 'posts',      title: 'نوشته‌ها',        cap: 'edit_posts', props: { type: 'post' } },
  { pattern: '/posts/new',       view: 'post-edit',  title: 'نوشته جدید',      cap: 'edit_posts', props: { type: 'post' } },
  { pattern: '/posts/:id',       view: 'post-edit',  title: 'ویرایش نوشته',    cap: 'edit_posts', props: { type: 'post' } },
  { pattern: '/pages',           view: 'posts',      title: 'برگه‌ها',          cap: 'edit_posts', props: { type: 'page' } },
  { pattern: '/pages/new',       view: 'post-edit',  title: 'برگه جدید',        cap: 'edit_posts', props: { type: 'page' } },
  { pattern: '/pages/:id',       view: 'post-edit',  title: 'ویرایش برگه',      cap: 'edit_posts', props: { type: 'page' } },
  { pattern: '/categories',      view: 'terms',      title: 'دسته‌بندی‌ها',     cap: 'manage_categories', props: { taxonomy: 'category' } },
  { pattern: '/tags',            view: 'terms',      title: 'برچسب‌ها',         cap: 'manage_categories', props: { taxonomy: 'tag' } },
  { pattern: '/media',           view: 'media',      title: 'کتابخانه رسانه',   cap: 'upload_files' },
  { pattern: '/comments',        view: 'comments',   title: 'دیدگاه‌ها',        cap: 'moderate_comments' },
  { pattern: '/menus',           view: 'menus',      title: 'فهرست‌های ناوبری', cap: 'manage_menus' },
  { pattern: '/users',           view: 'users',      title: 'کاربران',          cap: 'manage_users' },
  { pattern: '/settings',        view: 'settings',   title: 'تنظیمات',          cap: 'manage_settings' },
  { pattern: '/activity',        view: 'activity',   title: 'گزارش فعالیت‌ها',  cap: 'view_activity' },
  { pattern: '/profile',         view: 'profile',    title: 'پروفایل من',       cap: 'read' },
];

/**
 * تطبیق یک مسیر با الگوهای تعریف‌شده
 *
 * @returns {{route: object, params: object}|null}
 */
function matchRoute(path) {
  const segments = path.split('/').filter(Boolean);

  // ابتدا مسیرهای ثابت بررسی می‌شوند تا «/posts/new» به «/posts/:id» نخورد
  const exact = routes.find((r) => r.pattern === path);

  if (exact) {
    return { route: exact, params: {} };
  }

  for (const candidate of routes) {
    const patternSegments = candidate.pattern.split('/').filter(Boolean);

    if (patternSegments.length !== segments.length) continue;

    const params = {};
    let matched = true;

    for (let i = 0; i < patternSegments.length; i++) {
      if (patternSegments[i].startsWith(':')) {
        params[patternSegments[i].slice(1)] = decodeURIComponent(segments[i]);
      } else if (patternSegments[i] !== segments[i]) {
        matched = false;
        break;
      }
    }

    if (matched) {
      return { route: candidate, params };
    }
  }

  return null;
}

/** مسیر تطبیق‌یافته فعلی */
export let current = null;

/** شنوندگان تغییر مسیر */
const listeners = new Set();

export function onRouteChange(callback) {
  listeners.add(callback);

  return () => listeners.delete(callback);
}

/**
 * خواندن مسیر از نوار آدرس و به‌روزرسانی وضعیت
 */
export function resolveRoute() {
  const hash = window.location.hash.replace(/^#/, '') || '/';
  const [rawPath, rawQuery] = hash.split('?');

  const path = rawPath.startsWith('/') ? rawPath : `/${rawPath}`;
  const query = {};

  if (rawQuery) {
    new URLSearchParams(rawQuery).forEach((value, key) => { query[key] = value; });
  }

  const matched = matchRoute(path.replace(/\/+$/, '') || '/');

  route.path = path;
  route.query = query;
  route.params = matched?.params || {};
  current = matched?.route || null;

  listeners.forEach((callback) => callback(current, route));

  return current;
}

/**
 * رفتن به یک مسیر
 *
 * @param {string} path مسیر مقصد، مثلاً '/posts/12'
 * @param {{replace?: boolean}} options
 */
export function navigate(path, { replace = false } = {}) {
  const target = `#${path.startsWith('/') ? path : `/${path}`}`;

  if (window.location.hash === target) {
    return;
  }

  if (replace) {
    // جایگزینی بدون افزودن رکورد جدید به تاریخچه مرورگر
    history.replaceState(null, '', target);
    resolveRoute();
  } else {
    window.location.hash = target;
  }
}

export function startRouter() {
  window.addEventListener('hashchange', resolveRoute);
  resolveRoute();
}

export { routes };
