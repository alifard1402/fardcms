/**
 * گزارش فعالیت‌ها
 */

import { Icon } from '../icons.js';
import { Pagination, EmptyState, LoadingBlock } from '../components/ui.js';
import { api } from '../api.js';
import { notifyError } from '../store.js';

const { ref, onMounted } = Vue;

export const ActivityView = {
  name: 'ActivityView',
  components: { Icon, Pagination, EmptyState, LoadingBlock },
  setup() {
    const items = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 30, total_pages: 0 });
    const loading = ref(true);

    const load = async (page = 1) => {
      loading.value = true;

      try {
        const data = await api.dashboard.activity({ page, per_page: 30 });

        items.value = data.activity || [];
        meta.value = data.meta || meta.value;
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => load());

    /** آیکون و رنگ مناسب برای هر نوع رویداد */
    const actionStyle = (action) => {
      if (action.startsWith('delete') || action === 'trash_post') {
        return { icon: 'trash', tone: 'danger' };
      }

      if (action.startsWith('create') || action === 'register') {
        return { icon: 'plus', tone: 'success' };
      }

      if (action.startsWith('update') || action.startsWith('save')) {
        return { icon: 'edit', tone: 'info' };
      }

      if (action === 'login') return { icon: 'logout', tone: 'success' };
      if (action === 'logout') return { icon: 'logout', tone: '' };
      if (action.includes('password')) return { icon: 'key', tone: 'warning' };
      if (action.startsWith('comment')) return { icon: 'comment', tone: 'info' };
      if (action.includes('media')) return { icon: 'media', tone: 'info' };

      return { icon: 'activity', tone: '' };
    };

    return { items, meta, loading, load, actionStyle };
  },
  template: `
    <div>
      <div class="card">
        <div class="card-header">
          <Icon name="activity" :size="17" class="faint" />
          <span class="card-title">گزارش فعالیت‌ها</span>
          <div class="grow"></div>
          <button class="icon-btn" @click="load(meta.page)" title="به‌روزرسانی">
            <Icon name="refresh" :size="16" />
          </button>
        </div>

        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!items.length" icon="activity" title="گزارشی ثبت نشده است"
                    text="رویدادهای مهم سایت مانند ایجاد محتوا و ورود کاربران اینجا ثبت می‌شود." />

        <template v-else>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>رویداد</th>
                  <th class="nowrap">کاربر</th>
                  <th class="nowrap">آدرس IP</th>
                  <th class="nowrap">زمان</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="flex gap-sm">
                      <div class="stat-icon" :class="actionStyle(item.action).tone"
                           style="width:30px;height:30px;border-radius:9px;margin-bottom:0">
                        <Icon :name="actionStyle(item.action).icon" :size="15" />
                      </div>
                      <div style="min-width:0">
                        <div class="cell-title" style="font-weight:500">
                          {{ item.description || item.action }}
                        </div>
                        <div class="cell-meta mono">{{ item.action }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="nowrap small">{{ item.user_name || 'سیستم' }}</td>
                  <td class="nowrap"><span class="mono faint">{{ item.ip_address }}</span></td>
                  <td class="nowrap small muted">
                    {{ item.date_relative }}
                    <div class="cell-meta">{{ item.date_jalali }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="meta" @change="load" />
        </template>
      </div>
    </div>
  `,
};
