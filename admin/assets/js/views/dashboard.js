/**
 * پیشخوان — آمار، نمودار انتشار و خلاصه فعالیت‌ها
 */

import { Icon } from '../icons.js';
import { LoadingBlock, EmptyState } from '../components/ui.js';
import { api } from '../api.js';
import { store, can, notifyError } from '../store.js';
import { navigate } from '../router.js';

const { ref, computed, onMounted } = Vue;

export const DashboardView = {
  name: 'DashboardView',
  components: { Icon, LoadingBlock, EmptyState },
  setup() {
    const data = ref(null);
    const loading = ref(true);

    const load = async () => {
      loading.value = true;

      try {
        data.value = await api.dashboard.stats();
        store.counters.pendingComments = data.value.stats.pending_comments || 0;
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    /** کارت‌های آماری قابل نمایش برای این کاربر */
    const cards = computed(() => {
      if (!data.value) return [];

      const s = data.value.stats;

      const all = [
        { key: 'posts',    label: 'نوشته‌ها',        value: s.posts,    icon: 'posts',   tone: '',        route: '/posts' },
        { key: 'pages',    label: 'برگه‌ها',          value: s.pages,    icon: 'page',    tone: 'info',    route: '/pages' },
        { key: 'drafts',   label: 'پیش‌نویس‌ها',      value: s.drafts,   icon: 'edit',    tone: 'warning', route: '/posts?status=draft' },
        { key: 'comments', label: 'دیدگاه‌ها',        value: s.comments, icon: 'comment', tone: 'success', route: '/comments', cap: 'moderate_comments' },
        { key: 'media',    label: 'فایل‌های رسانه',   value: s.media,    icon: 'media',   tone: 'info',    route: '/media' },
        { key: 'users',    label: 'کاربران',          value: s.users,    icon: 'users',   tone: '',        route: '/users', cap: 'manage_users' },
        { key: 'views',    label: 'بازدید کل',        value: s.views,    icon: 'eye',     tone: 'success' },
      ];

      return all.filter((card) => !card.cap || can(card.cap));
    });

    /** بیشترین مقدار نمودار، برای مقیاس‌کردن ارتفاع ستون‌ها */
    const chartMax = computed(() => Math.max(1, ...(data.value?.chart || []).map((d) => d.count)));

    const barHeight = (count) => `${Math.max(3, Math.round((count / chartMax.value) * 100))}%`;

    const statusTone = (status) => ({
      publish: 'badge-success',
      draft:   'badge-muted',
      pending: 'badge-warning',
      private: 'badge-info',
      trash:   'badge-danger',
    }[status] || 'badge-muted');

    return {
      data, loading, cards, chartMax, barHeight, statusTone,
      store, can, navigate, load,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <LoadingBlock v-if="loading" />

      <template v-else-if="data">
        <!-- کارت‌های آماری -->
        <div class="stat-grid">
          <div v-for="card in cards" :key="card.key" class="stat-card"
               :style="card.route ? 'cursor:pointer' : ''"
               @click="card.route && navigate(card.route)">
            <div class="stat-icon" :class="card.tone"><Icon :name="card.icon" :size="19" /></div>
            <div class="stat-value">{{ fa(card.value) }}</div>
            <div class="stat-label">{{ card.label }}</div>
          </div>
        </div>

        <!-- هشدار دیدگاه‌های در انتظار -->
        <div class="alert alert-warning" v-if="data.stats.pending_comments > 0">
          <Icon name="alert" :size="18" />
          <div class="grow">
            {{ fa(data.stats.pending_comments) }} دیدگاه در انتظار بررسی است.
          </div>
          <button class="btn btn-sm btn-secondary" @click="navigate('/comments?status=pending')">
            بررسی دیدگاه‌ها
          </button>
        </div>

        <div class="split">
          <div class="stack">
            <!-- نمودار انتشار -->
            <div class="card">
              <div class="card-header">
                <Icon name="chart" :size="17" class="faint" />
                <span class="card-title">انتشار محتوا در ۳۰ روز گذشته</span>
                <button class="icon-btn" @click="load" title="به‌روزرسانی">
                  <Icon name="refresh" :size="16" />
                </button>
              </div>
              <div class="card-body">
                <div class="chart-wrap">
                  <div class="chart-bars">
                    <div v-for="day in data.chart" :key="day.date"
                         class="chart-bar"
                         :data-empty="day.count === 0"
                         :style="{ height: barHeight(day.count) }">
                      <span class="chart-tip">{{ day.label }} — {{ fa(day.count) }} نوشته</span>
                    </div>
                  </div>
                  <div class="chart-axis">
                    <span>{{ data.chart[0]?.label }}</span>
                    <span>{{ data.chart[Math.floor(data.chart.length / 2)]?.label }}</span>
                    <span>{{ data.chart[data.chart.length - 1]?.label }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- آخرین نوشته‌ها -->
            <div class="card">
              <div class="card-header">
                <Icon name="clock" :size="17" class="faint" />
                <span class="card-title">آخرین تغییرات</span>
                <button class="btn btn-sm btn-ghost" @click="navigate('/posts')">
                  همه نوشته‌ها <Icon name="chevronLeft" :size="14" />
                </button>
              </div>
              <div class="card-body tight">
                <EmptyState v-if="!data.recent.length" icon="posts" title="هنوز نوشته‌ای ندارید"
                            text="اولین نوشته خود را ایجاد کنید.">
                  <button class="btn btn-primary" @click="navigate('/posts/new')">
                    <Icon name="plus" :size="16" /> افزودن نوشته
                  </button>
                </EmptyState>

                <div class="simple-list" v-else>
                  <div v-for="post in data.recent" :key="post.id" class="simple-item"
                       style="cursor:pointer" @click="navigate('/posts/' + post.id)">
                    <div class="simple-body">
                      <div class="simple-title">{{ post.title }}</div>
                      <div class="simple-meta">
                        {{ post.date_relative }} · {{ post.author_name || 'بدون نویسنده' }}
                      </div>
                    </div>
                    <span class="badge" :class="statusTone(post.status)">{{ post.status_label }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="stack">
            <!-- دسترسی سریع -->
            <div class="card">
              <div class="card-header"><span class="card-title">دسترسی سریع</span></div>
              <div class="card-body flex-col gap-sm">
                <button class="btn btn-primary btn-block" @click="navigate('/posts/new')">
                  <Icon name="plus" :size="16" /> نوشته جدید
                </button>
                <button class="btn btn-secondary btn-block" @click="navigate('/pages/new')">
                  <Icon name="page" :size="16" /> برگه جدید
                </button>
                <button class="btn btn-secondary btn-block" @click="navigate('/media')">
                  <Icon name="upload" :size="16" /> آپلود رسانه
                </button>
                <a class="btn btn-ghost btn-block" :href="data.system.site_url" target="_blank" rel="noopener">
                  <Icon name="external" :size="16" /> مشاهده سایت
                </a>
              </div>
            </div>

            <!-- پربازدیدترین‌ها -->
            <div class="card" v-if="data.popular.length">
              <div class="card-header">
                <Icon name="eye" :size="17" class="faint" />
                <span class="card-title">پربازدیدترین</span>
              </div>
              <div class="card-body tight">
                <div class="simple-list">
                  <div v-for="post in data.popular" :key="post.id" class="simple-item"
                       style="cursor:pointer" @click="navigate('/posts/' + post.id)">
                    <div class="simple-body">
                      <div class="simple-title">{{ post.title }}</div>
                      <div class="simple-meta">{{ fa(post.views) }} بازدید</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- دیدگاه‌های در انتظار -->
            <div class="card" v-if="data.pending_comment_list?.length">
              <div class="card-header">
                <Icon name="comment" :size="17" class="faint" />
                <span class="card-title">در انتظار تأیید</span>
              </div>
              <div class="card-body tight">
                <div class="simple-list">
                  <div v-for="c in data.pending_comment_list" :key="c.id" class="simple-item"
                       style="cursor:pointer" @click="navigate('/comments?status=pending')">
                    <div class="simple-body">
                      <div class="simple-title">{{ c.author_name }}</div>
                      <div class="simple-meta truncate">{{ c.excerpt }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- گزارش فعالیت -->
            <div class="card" v-if="data.activity?.length">
              <div class="card-header">
                <Icon name="activity" :size="17" class="faint" />
                <span class="card-title">فعالیت‌های اخیر</span>
                <button class="btn btn-sm btn-ghost" @click="navigate('/activity')">
                  <Icon name="chevronLeft" :size="14" />
                </button>
              </div>
              <div class="card-body tight">
                <div class="simple-list">
                  <div v-for="item in data.activity" :key="item.id" class="simple-item">
                    <div class="simple-body">
                      <div class="simple-title" style="font-weight:500">{{ item.description }}</div>
                      <div class="simple-meta">{{ item.user_name || 'سیستم' }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- اطلاعات سیستم -->
            <div class="card">
              <div class="card-header"><span class="card-title">اطلاعات سیستم</span></div>
              <div class="card-body">
                <div class="flex" style="justify-content:space-between;margin-bottom:8px">
                  <span class="small muted">نسخه فرد سی‌ام‌اس</span>
                  <span class="small mono">{{ data.system.cms_version }}</span>
                </div>
                <div class="flex" style="justify-content:space-between;margin-bottom:8px">
                  <span class="small muted">نسخه PHP</span>
                  <span class="small mono">{{ data.system.php_version }}</span>
                </div>
                <div class="flex" style="justify-content:space-between">
                  <span class="small muted">قالب فعال</span>
                  <span class="small mono">{{ data.system.active_theme }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  `,
};
