/**
 * فهرست نوشته‌ها و برگه‌ها
 *
 * یک کامپوننت برای هر دو نوع محتوا استفاده می‌شود؛ نوع از پارامتر مسیر
 * خوانده می‌شود.
 */

import { Icon } from '../icons.js';
import {
  Pagination, EmptyState, LoadingBlock, SearchInput, Checkbox, ConfirmDialog,
} from '../components/ui.js';
import { api } from '../api.js';
import { can, notify, notifyError } from '../store.js';
import { navigate, route } from '../router.js';

const { ref, computed, watch, onMounted } = Vue;

export const PostsView = {
  name: 'PostsView',
  components: { Icon, Pagination, EmptyState, LoadingBlock, SearchInput, Checkbox, ConfirmDialog },
  props: {
    type: { type: String, default: 'post' },
  },
  setup(props) {
    const posts = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 20, total_pages: 0 });
    const counts = ref({});
    const loading = ref(true);
    const search = ref('');
    const status = ref(route.query.status || 'any');
    const orderby = ref('date');
    const order = ref('DESC');
    const selected = ref([]);
    const confirm = ref(null);
    const busy = ref(false);

    const isPage = computed(() => props.type === 'page');
    const labels = computed(() => (isPage.value
      ? { one: 'برگه', many: 'برگه‌ها', addRoute: '/pages/new', editBase: '/pages/' }
      : { one: 'نوشته', many: 'نوشته‌ها', addRoute: '/posts/new', editBase: '/posts/' }));

    const load = async (page = 1) => {
      loading.value = true;
      selected.value = [];

      try {
        const data = await api.posts.list({
          type: props.type,
          status: status.value,
          search: search.value,
          orderby: orderby.value,
          order: order.value,
          page,
          per_page: 20,
        });

        posts.value = data.posts || [];
        meta.value = data.meta || meta.value;
        counts.value = data.counts || {};
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => load());

    // تغییر نوع محتوا (نوشته ↔ برگه) فهرست را از ابتدا بارگذاری می‌کند
    watch(() => props.type, () => {
      status.value = 'any';
      search.value = '';
      load(1);
    });

    const setStatus = (value) => {
      status.value = value;
      load(1);
    };

    /** تغییر ستون مرتب‌سازی؛ کلیک دوباره جهت را برمی‌گرداند */
    const sortBy = (column) => {
      if (orderby.value === column) {
        order.value = order.value === 'ASC' ? 'DESC' : 'ASC';
      } else {
        orderby.value = column;
        order.value = 'DESC';
      }

      load(meta.value.page);
    };

    /* ─── انتخاب گروهی ─────────────────────────────────────── */
    const allSelected = computed(
      () => posts.value.length > 0 && selected.value.length === posts.value.length
    );

    const toggleAll = () => {
      selected.value = allSelected.value ? [] : posts.value.map((p) => p.id);
    };

    const toggleOne = (id) => {
      const index = selected.value.indexOf(id);

      if (index === -1) {
        selected.value.push(id);
      } else {
        selected.value.splice(index, 1);
      }
    };

    /* ─── عملیات ───────────────────────────────────────────── */
    const askDelete = (post) => {
      const permanent = post.status === 'trash';

      confirm.value = {
        message: permanent
          ? `«${post.title}» برای همیشه حذف می‌شود. این عملیات بازگشت‌پذیر نیست.`
          : `«${post.title}» به زباله‌دان منتقل می‌شود.`,
        label: permanent ? 'حذف همیشگی' : 'انتقال به زباله‌دان',
        danger: permanent,
        run: () => api.posts.remove(post.id, permanent ? 'force' : 'trash'),
      };
    };

    const askBulk = (action) => {
      const messages = {
        trash:   'موارد انتخاب‌شده به زباله‌دان منتقل می‌شوند.',
        restore: 'موارد انتخاب‌شده بازگردانی می‌شوند.',
        force:   'موارد انتخاب‌شده برای همیشه حذف می‌شوند. این عملیات بازگشت‌پذیر نیست.',
        publish: 'موارد انتخاب‌شده منتشر می‌شوند.',
        draft:   'موارد انتخاب‌شده به پیش‌نویس تغییر می‌کنند.',
      };

      const count = selected.value.length.toLocaleString('fa-IR');

      confirm.value = {
        message: `${count} مورد انتخاب شده. ${messages[action]}`,
        label: 'تأیید',
        danger: action === 'force',
        run: () => api.posts.bulk(selected.value, action),
      };
    };

    const runConfirm = async () => {
      if (!confirm.value) return;

      busy.value = true;

      try {
        const result = await confirm.value.run();
        notify(result?.message || 'انجام شد');
        confirm.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const restore = async (post) => {
      try {
        await api.posts.remove(post.id, 'restore');
        notify('محتوا بازگردانی شد');
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      }
    };

    /* ─── نمایش ────────────────────────────────────────────── */
    const statusTabs = computed(() => {
      const tabs = [{ value: 'any', label: 'همه', count: counts.value.all }];

      const order = [
        ['publish', 'منتشرشده'],
        ['draft', 'پیش‌نویس'],
        ['pending', 'در انتظار'],
        ['private', 'خصوصی'],
        ['trash', 'زباله‌دان'],
      ];

      order.forEach(([value, label]) => {
        if (counts.value[value] > 0) {
          tabs.push({ value, label, count: counts.value[value] });
        }
      });

      return tabs;
    });

    const statusTone = (s) => ({
      publish: 'badge-success',
      draft:   'badge-muted',
      pending: 'badge-warning',
      private: 'badge-info',
      trash:   'badge-danger',
    }[s] || 'badge-muted');

    return {
      posts, meta, counts, loading, search, status, orderby, order, selected, confirm, busy,
      isPage, labels, statusTabs, statusTone, allSelected,
      load, setStatus, sortBy, toggleAll, toggleOne,
      askDelete, askBulk, runConfirm, restore,
      can, navigate,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <SearchInput v-model="search" :placeholder="'جستجو در ' + labels.many + '…'" @search="load(1)" />

        <div class="status-filters">
          <button v-for="tab in statusTabs" :key="tab.value"
                  :class="['status-filter', { active: status === tab.value }]"
                  @click="setStatus(tab.value)">
            {{ tab.label }}<span class="count" v-if="tab.count">({{ fa(tab.count) }})</span>
          </button>
        </div>

        <div class="toolbar-spacer"></div>

        <button class="btn btn-primary" @click="navigate(labels.addRoute)">
          <Icon name="plus" :size="16" /> {{ labels.one }} جدید
        </button>
      </div>

      <!-- نوار عملیات گروهی -->
      <div class="alert alert-info" v-if="selected.length">
        <Icon name="info" :size="18" />
        <div class="grow">{{ fa(selected.length) }} مورد انتخاب شده</div>
        <div class="flex gap-xs" style="flex-wrap:wrap">
          <button class="btn btn-sm btn-secondary" @click="askBulk('publish')"
                  v-if="status !== 'trash' && can('publish_posts')">انتشار</button>
          <button class="btn btn-sm btn-secondary" @click="askBulk('draft')"
                  v-if="status !== 'trash'">پیش‌نویس</button>
          <button class="btn btn-sm btn-secondary" @click="askBulk('restore')"
                  v-if="status === 'trash'">بازگردانی</button>
          <button class="btn btn-sm btn-danger" @click="askBulk(status === 'trash' ? 'force' : 'trash')">
            {{ status === 'trash' ? 'حذف همیشگی' : 'زباله‌دان' }}
          </button>
          <button class="btn btn-sm btn-ghost" @click="selected = []">لغو انتخاب</button>
        </div>
      </div>

      <div class="card">
        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!posts.length"
                    :icon="isPage ? 'page' : 'posts'"
                    :title="search ? 'نتیجه‌ای یافت نشد' : 'هنوز ' + labels.one + 'ای ندارید'"
                    :text="search ? 'عبارت جستجو را تغییر دهید یا فیلترها را بردارید.' : 'اولین ' + labels.one + ' خود را ایجاد کنید.'">
          <button class="btn btn-primary" @click="navigate(labels.addRoute)" v-if="!search">
            <Icon name="plus" :size="16" /> {{ labels.one }} جدید
          </button>
        </EmptyState>

        <template v-else>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th class="col-narrow">
                    <Checkbox :model-value="allSelected" @update:model-value="toggleAll" />
                  </th>
                  <th style="cursor:pointer" @click="sortBy('title')">
                    عنوان
                    <Icon v-if="orderby === 'title'" :size="12"
                          :name="order === 'ASC' ? 'chevronDown' : 'chevronDown'" />
                  </th>
                  <th class="nowrap">نویسنده</th>
                  <th class="nowrap" v-if="!isPage">دسته‌ها</th>
                  <th class="nowrap">وضعیت</th>
                  <th class="nowrap" style="cursor:pointer" @click="sortBy('views')">بازدید</th>
                  <th class="nowrap" style="cursor:pointer" @click="sortBy('date')">تاریخ</th>
                  <th class="col-actions"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="post in posts" :key="post.id">
                  <td>
                    <Checkbox :model-value="selected.includes(post.id)"
                              @update:model-value="toggleOne(post.id)" />
                  </td>
                  <td>
                    <div class="cell-title" style="cursor:pointer"
                         @click="navigate(labels.editBase + post.id)">{{ post.title }}</div>
                    <div class="cell-meta">
                      {{ post.comment_count > 0 ? fa(post.comment_count) + ' دیدگاه · ' : '' }}
                      <span class="mono">{{ post.slug }}</span>
                    </div>
                  </td>
                  <td class="nowrap muted small">{{ post.author_name || '—' }}</td>
                  <td v-if="!isPage">
                    <div class="flex gap-xs" style="flex-wrap:wrap" v-if="post.categories?.length">
                      <span class="badge badge-muted" v-for="cat in post.categories" :key="cat.id">
                        {{ cat.name }}
                      </span>
                    </div>
                    <span class="faint small" v-else>—</span>
                  </td>
                  <td><span class="badge" :class="statusTone(post.status)">{{ post.status_label }}</span></td>
                  <td class="nowrap muted small">{{ fa(post.views) }}</td>
                  <td class="nowrap muted small">{{ post.date_jalali }}</td>
                  <td class="col-actions">
                    <div class="row-actions">
                      <a class="icon-btn" v-if="post.status === 'publish'" :href="post.url"
                         target="_blank" rel="noopener" title="مشاهده">
                        <Icon name="eye" :size="16" />
                      </a>
                      <button class="icon-btn" v-if="post.status === 'trash'"
                              @click="restore(post)" title="بازگردانی">
                        <Icon name="restore" :size="16" />
                      </button>
                      <button class="icon-btn" v-else
                              @click="navigate(labels.editBase + post.id)" title="ویرایش">
                        <Icon name="edit" :size="16" />
                      </button>
                      <button class="icon-btn" @click="askDelete(post)" title="حذف">
                        <Icon name="trash" :size="16" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :meta="meta" @change="load" />
        </template>
      </div>

      <ConfirmDialog v-if="confirm"
                     :message="confirm.message"
                     :confirm-label="confirm.label"
                     :danger="confirm.danger"
                     :busy="busy"
                     @confirm="runConfirm"
                     @cancel="confirm = null" />
    </div>
  `,
};
