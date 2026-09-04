/**
 * وضعیت مشترک پنل مدیریت
 *
 * یک شیء reactive سراسری که همه بخش‌ها به آن دسترسی دارند؛ برای پنلی
 * با این اندازه سبک‌تر و ساده‌تر از یک کتابخانه مدیریت وضعیت است.
 */

const { reactive, computed } = Vue;

export const store = reactive({
  /** کاربر وارد‌شده */
  user: null,
  /** آیا بارگذاری اولیه تمام شده است؟ */
  ready: false,
  /** تنظیمات عمومی سایت */
  settings: {},
  /** فهرست نقش‌های قابل انتخاب */
  roles: [],
  /** پیام‌های شناور */
  toasts: [],
  /** وضعیت نوار کناری */
  sidebarCollapsed: localStorage.getItem('fardcms_sidebar') === '1',
  sidebarMobileOpen: false,
  /** پوسته فعال: dark یا light */
  theme: localStorage.getItem('fardcms_theme') || 'dark',
  /** شمارنده‌های نمایش‌داده‌شده روی نوار کناری */
  counters: { pendingComments: 0 },
});

/** آیا کاربر دسترسی مشخصی دارد؟ */
export function can(capability) {
  return Boolean(store.user?.caps?.includes(capability));
}

/** آیا کاربر هر یک از این دسترسی‌ها را دارد؟ */
export function canAny(...capabilities) {
  return capabilities.some(can);
}

let toastId = 0;

/**
 * نمایش یک پیام شناور
 *
 * @param {string} message متن پیام
 * @param {'success'|'error'|'info'} type نوع پیام
 */
export function notify(message, type = 'success') {
  const id = ++toastId;
  store.toasts.push({ id, message, type });

  // پیام‌های خطا مدت بیشتری نمایش داده می‌شوند تا خوانده شوند
  setTimeout(() => dismissToast(id), type === 'error' ? 6000 : 3600);
}

export function dismissToast(id) {
  const index = store.toasts.findIndex((t) => t.id === id);

  if (index !== -1) {
    store.toasts.splice(index, 1);
  }
}

/** تبدیل خطای API به پیام شناور */
export function notifyError(error) {
  notify(error?.message || 'خطای ناشناخته رخ داد', 'error');
}

/** جابه‌جایی بین پوسته تیره و روشن */
export function toggleTheme() {
  store.theme = store.theme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('fardcms_theme', store.theme);
  applyTheme();
}

export function applyTheme() {
  document.documentElement.setAttribute('data-theme', store.theme);
}

/** باز و بسته کردن نوار کناری */
export function toggleSidebar() {
  if (window.innerWidth <= 860) {
    store.sidebarMobileOpen = !store.sidebarMobileOpen;
    return;
  }

  store.sidebarCollapsed = !store.sidebarCollapsed;
  localStorage.setItem('fardcms_sidebar', store.sidebarCollapsed ? '1' : '0');
}

/** حروف اول نام برای نمایش در جای آواتار */
export const userInitials = computed(() => {
  const name = store.user?.name || '';

  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('');
});
