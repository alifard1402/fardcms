/**
 * برنامه اصلی پنل مدیریت
 */

import { Icon } from './icons.js';
import { ToastStack } from './components/ui.js';
import { api, setCsrfToken, ApiError } from './api.js';
import {
  store, can, notify, notifyError, userInitials,
  toggleTheme, applyTheme, toggleSidebar,
} from './store.js';
import { route, current, routes, navigate, startRouter, onRouteChange, resolveRoute } from './router.js';

import { DashboardView } from './views/dashboard.js';
import { PostsView } from './views/posts.js';
import { PostEditView } from './views/post-edit.js';
import { MediaView } from './views/media.js';
import { CommentsView } from './views/comments.js';
import { TermsView } from './views/terms.js';
import { MenusView } from './views/menus.js';
import { UsersView } from './views/users.js';
import { SettingsView } from './views/settings.js';
import { ProfileView } from './views/profile.js';
import { ActivityView } from './views/activity.js';

/**
 * نسخه‌ای که این کد انتظار دارد در admin.css ببیند.
 *
 * فایل استایل مقدار --fardcms-css را تعریف می‌کند. اگر مرورگر یا CDN
 * نسخه قدیمی CSS را نگه داشته باشد، مهر ?v= داخل index.php هم
 * ممکن است قدیمی مانده باشد و اصلاح‌های ظاهری هرگز به کاربر نرسند —
 * کاربری که فایل‌ها را درست هم آپلود کرده باشد گمان می‌کند اشکال باقی
 * است. ماژول‌های js همیشه بازبینی می‌شوند، پس این بررسی اینجا انجام
 * می‌شود تا خودِ پنل بتواند استایل تازه را دوباره بگیرد.
 */
const EXPECTED_CSS_VERSION = '1.0.6';

/** مقدار --fardcms-css از استایلِ اعمال‌شده فعلی */
function loadedCssVersion() {
    return getComputedStyle(document.documentElement)
        .getPropertyValue('--fardcms-css')
        .trim()
        .replace(/^["']|["']$/g, '');
}

/** در صورت قدیمی بودن استایل، یک بار آن را با آدرس تازه دوباره می‌گیرد */
function ensureFreshStylesheet() {
    if (loadedCssVersion() === EXPECTED_CSS_VERSION) return;

    const link = document.querySelector('link[rel="stylesheet"][href*="admin.css"]');
    if (!link) return;

    const url = new URL(link.getAttribute('href'), location.href);
    url.searchParams.set('v', EXPECTED_CSS_VERSION);
    url.searchParams.set('cb', Date.now().toString(36)); // عبور از کشِ نشانی‌دار

    const fresh = document.createElement('link');
    fresh.rel = 'stylesheet';
    fresh.href = url.href;
    fresh.addEventListener('load', () => {
        link.remove();
        if (loadedCssVersion() !== EXPECTED_CSS_VERSION) {
            console.warn(
                '[فرد سی‌ام‌اس] فایل admin/assets/css/admin.css روی سرور قدیمی است. ' +
                'نسخه موردانتظار ' + EXPECTED_CSS_VERSION + '، نسخه موجود «' +
                (loadedCssVersion() || 'نامشخص') + '». فایل‌های نسخه تازه را دوباره آپلود کنید.'
            );
        }
    });
    fresh.addEventListener('error', () => fresh.remove());
    document.head.appendChild(fresh);
}

ensureFreshStylesheet();

const { createApp, ref, computed, onMounted, watch } = Vue;

/** نگاشت نام نما به کامپوننت */
const viewComponents = {
  dashboard:   DashboardView,
  posts:       PostsView,
  'post-edit': PostEditView,
  media:       MediaView,
  comments:    CommentsView,
  terms:       TermsView,
  menus:       MenusView,
  users:       UsersView,
  settings:    SettingsView,
  profile:     ProfileView,
  activity:    ActivityView,
};

/** ساختار نوار کناری */
const navSections = [
  {
    label: '',
    items: [
      { route: '/', label: 'پیشخوان', icon: 'dashboard', cap: 'edit_posts' },
    ],
  },
  {
    label: 'محتوا',
    items: [
      { route: '/posts', label: 'نوشته‌ها', icon: 'posts', cap: 'edit_posts' },
      { route: '/pages', label: 'برگه‌ها', icon: 'page', cap: 'edit_posts' },
      { route: '/categories', label: 'دسته‌بندی‌ها', icon: 'folder', cap: 'manage_categories' },
      { route: '/tags', label: 'برچسب‌ها', icon: 'tag', cap: 'manage_categories' },
      { route: '/media', label: 'رسانه', icon: 'media', cap: 'upload_files' },
      { route: '/comments', label: 'دیدگاه‌ها', icon: 'comment', cap: 'moderate_comments', badge: 'pendingComments' },
    ],
  },
  {
    label: 'نمایش',
    items: [
      { route: '/menus', label: 'فهرست‌ها', icon: 'menu', cap: 'manage_menus' },
    ],
  },
  {
    label: 'مدیریت',
    items: [
      { route: '/users', label: 'کاربران', icon: 'users', cap: 'manage_users' },
      { route: '/settings', label: 'تنظیمات', icon: 'settings', cap: 'manage_settings' },
      { route: '/activity', label: 'گزارش فعالیت', icon: 'activity', cap: 'view_activity' },
    ],
  },
];

const App = {
  name: 'AdminApp',
  components: { Icon, ToastStack, ...viewComponents },
  setup() {
    const bootError = ref('');
    const userMenuOpen = ref(false);
    const currentRoute = ref(null);
    const accessDenied = ref(false);

    /* ─── راه‌اندازی ───────────────────────────────────────── */
    onMounted(async () => {
      applyTheme();

      try {
        const data = await api.auth.me();

        if (!data.authenticated) {
          // کاربر وارد نشده است؛ به صفحه ورود هدایت می‌شود
          window.location.replace('../login.php?redirect=admin');
          return;
        }

        if (!data.can_access_admin) {
          bootError.value = 'حساب شما به پنل مدیریت دسترسی ندارد.';
          store.ready = true;
          return;
        }

        store.user = data.user;
        store.settings = data.settings || {};
        store.roles = data.roles || [];
        setCsrfToken(data.csrf_token);

        document.title = `پیشخوان — ${store.settings.site_title || 'فرد سی‌ام‌اس'}`;
      } catch (error) {
        // نشست منقضی یا خطای شبکه
        if (error instanceof ApiError && error.status === 401) {
          window.location.replace('../login.php?redirect=admin');
          return;
        }

        bootError.value = error.message || 'بارگذاری پنل مدیریت ممکن نشد.';
      } finally {
        store.ready = true;
      }

      if (!bootError.value) {
        startRouter();
      }
    });

    /* ─── تغییر مسیر ───────────────────────────────────────── */
    onRouteChange((matched, activeRoute) => {
      // مسیر ناشناخته به پیشخوان برمی‌گردد
      if (!matched) {
        navigate('/', { replace: true });
        return;
      }

      // بررسی دسترسی پیش از نمایش صفحه
      accessDenied.value = Boolean(matched.cap) && !can(matched.cap);
      currentRoute.value = matched;
      userMenuOpen.value = false;

      window.scrollTo({ top: 0, behavior: 'instant' });

      const siteTitle = store.settings.site_title || 'فرد سی‌ام‌اس';
      document.title = `${matched.title} — ${siteTitle}`;
    });

    /** خصوصیات ورودی نمای فعلی */
    const viewProps = computed(() => {
      if (!currentRoute.value) return {};

      return { ...(currentRoute.value.props || {}), ...route.params };
    });

    /** آیا این آیتم نوار کناری فعال است؟ */
    const isActive = (item) => {
      if (item.route === '/') return route.path === '/';

      return route.path === item.route || route.path.startsWith(`${item.route}/`);
    };

    /** بخش‌های نوار کناری پس از فیلتر دسترسی */
    const visibleSections = computed(
      () => navSections
        .map((section) => ({
          ...section,
          items: section.items.filter((item) => !item.cap || can(item.cap)),
        }))
        .filter((section) => section.items.length > 0)
    );

    /* ─── خروج ─────────────────────────────────────────────── */
    const logout = async () => {
      try {
        const data = await api.auth.logout();
        window.location.replace(data.redirect || '../login.php');
      } catch (error) {
        notifyError(error);
      }
    };

    /* ─── میان‌برهای صفحه‌کلید ─────────────────────────────── */
    const onKeydown = (event) => {
      // Ctrl+S یا Cmd+S: صفحه‌های ویرایش خودشان ذخیره را مدیریت می‌کنند،
      // اما ذخیره پیش‌فرض مرورگر باید متوقف شود
      if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        if (currentRoute.value?.view === 'post-edit') {
          event.preventDefault();
          document.querySelector('.toolbar .btn-primary')?.click();
        }
      }
    };

    window.addEventListener('keydown', onKeydown);

    // بستن منوی کاربر با کلیک بیرون از آن
    window.addEventListener('click', (event) => {
      if (userMenuOpen.value && !event.target.closest('.user-menu')) {
        userMenuOpen.value = false;
      }
    });

    return {
      store, route, currentRoute, viewProps, visibleSections,
      bootError, userMenuOpen, accessDenied, userInitials,
      can, isActive, navigate, logout, toggleTheme, toggleSidebar,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <!-- بارگذاری اولیه -->
    <div v-if="!store.ready" style="min-height:100vh;display:grid;place-items:center">
      <div class="spinner dark lg"></div>
    </div>

    <!-- خطای راه‌اندازی -->
    <div v-else-if="bootError" style="min-height:100vh;display:grid;place-items:center;padding:24px">
      <div class="card" style="max-width:440px;width:100%">
        <div class="card-body center">
          <div class="empty-icon" style="margin:0 auto 17px;background:var(--danger-soft);color:var(--danger)">
            <Icon name="alert" :size="29" />
          </div>
          <div class="empty-title">دسترسی ممکن نیست</div>
          <p class="empty-text">{{ bootError }}</p>
          <div class="flex gap-sm" style="justify-content:center">
            <a class="btn btn-secondary" href="../">بازگشت به سایت</a>
            <a class="btn btn-primary" href="../login.php">ورود با حساب دیگر</a>
          </div>
        </div>
      </div>
    </div>

    <!-- پنل مدیریت -->
    <div v-else :class="['admin-layout', { 'sidebar-collapsed': store.sidebarCollapsed }]">
      <!-- نوار کناری -->
      <aside id="admin-sidebar" :class="['sidebar', { 'mobile-open': store.sidebarMobileOpen }]">
        <div class="sidebar-brand">
          <div class="brand-mark">✦</div>
          <div class="brand-text">
            <div class="brand-name">{{ store.settings.site_title || 'فرد سی‌ام‌اس' }}</div>
            <div class="brand-sub">پنل مدیریت</div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <template v-for="(section, i) in visibleSections" :key="i">
            <div class="nav-section-label" v-if="section.label">{{ section.label }}</div>
            <button v-for="item in section.items" :key="item.route"
                    :class="['nav-item', { active: isActive(item) }]"
                    @click="navigate(item.route); store.sidebarMobileOpen = false">
              <Icon :name="item.icon" :size="18" />
              <span class="nav-label">{{ item.label }}</span>
              <span class="nav-badge" v-if="item.badge && store.counters[item.badge] > 0">
                {{ fa(store.counters[item.badge]) }}
              </span>
            </button>
          </template>
        </nav>

        <div class="sidebar-footer">
          <a class="nav-item" href="../" target="_blank" rel="noopener">
            <Icon name="external" :size="18" />
            <span class="nav-label sidebar-footer-text">مشاهده سایت</span>
          </a>
        </div>
      </aside>

      <!-- پرده پشت نوار کناری در موبایل -->
      <div class="sidebar-scrim" v-if="store.sidebarMobileOpen"
           @click="store.sidebarMobileOpen = false"></div>

      <!-- ناحیه اصلی -->
      <div class="main-area">
        <header class="topbar">
          <button class="icon-btn" id="admin-nav-toggle" @click="toggleSidebar"
                  aria-controls="admin-sidebar"
                  :aria-expanded="String(store.sidebarMobileOpen)"
                  :aria-label="store.sidebarMobileOpen ? 'بستن فهرست' : 'باز کردن فهرست'">
            <Icon name="menu" :size="19" />
          </button>

          <div class="page-heading">
            <div class="page-title">{{ currentRoute?.title || 'پیشخوان' }}</div>
          </div>

          <button class="icon-btn" @click="toggleTheme"
                  :aria-label="store.theme === 'dark' ? 'پوسته روشن' : 'پوسته تیره'"
                  :title="store.theme === 'dark' ? 'پوسته روشن' : 'پوسته تیره'">
            <Icon :name="store.theme === 'dark' ? 'sun' : 'moon'" :size="19" />
          </button>

          <div class="user-menu">
            <button class="user-trigger" @click.stop="userMenuOpen = !userMenuOpen">
              <img v-if="store.user?.avatar" :src="store.user.avatar" class="avatar" alt="">
              <div v-else class="avatar">{{ userInitials }}</div>
              <span class="user-name-label">{{ store.user?.name }}</span>
              <Icon name="chevronDown" :size="15" class="faint" />
            </button>

            <div class="dropdown" v-if="userMenuOpen">
              <div class="dropdown-header">
                <div class="bold small">{{ store.user?.name }}</div>
                <div class="tiny faint ltr">{{ store.user?.email }}</div>
                <span class="badge badge-accent" style="margin-top:7px">{{ store.user?.role_label }}</span>
              </div>
              <button class="dropdown-item" @click="navigate('/profile')">
                <Icon name="user" :size="16" /> پروفایل من
              </button>
              <a class="dropdown-item" href="../" target="_blank" rel="noopener">
                <Icon name="external" :size="16" /> مشاهده سایت
              </a>
              <div class="dropdown-divider"></div>
              <button class="dropdown-item danger" @click="logout">
                <Icon name="logout" :size="16" /> خروج از حساب
              </button>
            </div>
          </div>
        </header>

        <main class="page-content">
          <!-- عدم دسترسی به این بخش -->
          <div class="card" v-if="accessDenied">
            <div class="card-body center" style="padding:52px 24px">
              <div class="empty-icon" style="margin:0 auto 17px;background:var(--danger-soft);color:var(--danger)">
                <Icon name="lock" :size="29" />
              </div>
              <div class="empty-title">دسترسی ندارید</div>
              <p class="empty-text">
                نقش «{{ store.user?.role_label }}» اجازه مشاهده این بخش را ندارد.
              </p>
              <button class="btn btn-primary" @click="navigate('/')">بازگشت به پیشخوان</button>
            </div>
          </div>

          <!-- نمای فعلی -->
          <component v-else-if="currentRoute"
                     :is="currentRoute.view"
                     :key="route.path"
                     v-bind="viewProps" />
        </main>
      </div>
    </div>

    <ToastStack />
  `,
};

createApp(App).mount('#app');
