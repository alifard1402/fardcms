/**
 * مدیریت دیدگاه‌ها — تأیید، رد، ویرایش و حذف
 */

import { Icon } from '../icons.js';
import {
  Modal, Pagination, EmptyState, LoadingBlock, SearchInput, Checkbox, ConfirmDialog,
} from '../components/ui.js';
import { api } from '../api.js';
import { store, notify, notifyError } from '../store.js';
import { route } from '../router.js';

const { ref, computed, onMounted } = Vue;

export const CommentsView = {
  name: 'CommentsView',
  components: { Icon, Modal, Pagination, EmptyState, LoadingBlock, SearchInput, Checkbox, ConfirmDialog },
  setup() {
    const comments = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 20, total_pages: 0 });
    const counts = ref({});
    const loading = ref(true);
    const search = ref('');
    const status = ref(route.query.status || 'all');
    const selected = ref([]);
    const editing = ref(null);
    const editText = ref('');
    const confirmAction = ref(null);
    const busy = ref(false);

    const load = async (page = 1) => {
      loading.value = true;
      selected.value = [];

      try {
        const data = await api.comments.list({
          status: status.value,
          search: search.value,
          page,
          per_page: 20,
        });

        comments.value = data.comments || [];
        meta.value = data.meta || meta.value;
        counts.value = data.counts || {};
        store.counters.pendingComments = counts.value.pending || 0;
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => load());

    const setStatus = (value) => {
      status.value = value;
      load(1);
    };

    /* ─── انتخاب گروهی ─────────────────────────────────────── */
    const allSelected = computed(
      () => comments.value.length > 0 && selected.value.length === comments.value.length
    );

    const toggleAll = () => {
      selected.value = allSelected.value ? [] : comments.value.map((c) => c.id);
    };

    const toggleOne = (id) => {
      const index = selected.value.indexOf(id);

      if (index === -1) {
        selected.value.push(id);
      } else {
        selected.value.splice(index, 1);
      }
    };

    /* ─── عملیات بازبینی ───────────────────────────────────── */
    const moderate = async (ids, action) => {
      try {
        const data = await api.comments.moderate({ ids, action });

        notify(data?.message || 'انجام شد');
        counts.value = data.counts || counts.value;
        store.counters.pendingComments = counts.value.pending || 0;

        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      }
    };

    const askDelete = (ids) => {
      const count = ids.length;

      confirmAction.value = {
        message: count === 1
          ? 'این دیدگاه برای همیشه حذف می‌شود. پاسخ‌های آن حفظ خواهند شد.'
          : `${count.toLocaleString('fa-IR')} دیدگاه برای همیشه حذف می‌شوند.`,
        run: () => api.comments.moderate({ ids, action: 'delete' }),
      };
    };

    const runConfirm = async () => {
      busy.value = true;

      try {
        const data = await confirmAction.value.run();

        notify(data?.message || 'حذف شد');
        confirmAction.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    /* ─── ویرایش متن ───────────────────────────────────────── */
    const openEdit = (comment) => {
      editing.value = comment;
      editText.value = comment.content;
    };

    const saveEdit = async () => {
      busy.value = true;

      try {
        await api.comments.moderate({ id: editing.value.id, action: 'edit', content: editText.value });

        notify('دیدگاه ویرایش شد');
        editing.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const statusTabs = computed(() => [
      { value: 'all', label: 'همه', count: counts.value.all },
      { value: 'pending', label: 'در انتظار', count: counts.value.pending },
      { value: 'approved', label: 'تأییدشده', count: counts.value.approved },
      { value: 'spam', label: 'هرزنامه', count: counts.value.spam },
      { value: 'trash', label: 'زباله‌دان', count: counts.value.trash },
    ]);

    const statusTone = (s) => ({
      approved: 'badge-success',
      pending:  'badge-warning',
      spam:     'badge-danger',
      trash:    'badge-muted',
    }[s] || 'badge-muted');

    return {
      comments, meta, counts, loading, search, status, selected, editing, editText,
      confirmAction, busy, statusTabs, statusTone, allSelected,
      load, setStatus, toggleAll, toggleOne, moderate, askDelete, runConfirm, openEdit, saveEdit,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <SearchInput v-model="search" placeholder="جستجو در دیدگاه‌ها…" @search="load(1)" />

        <div class="status-filters">
          <button v-for="tab in statusTabs" :key="tab.value"
                  :class="['status-filter', { active: status === tab.value }]"
                  @click="setStatus(tab.value)">
            {{ tab.label }}<span class="count" v-if="tab.count">({{ fa(tab.count) }})</span>
          </button>
        </div>
      </div>

      <!-- عملیات گروهی -->
      <div class="alert alert-info" v-if="selected.length">
        <Icon name="info" :size="18" />
        <div class="grow">{{ fa(selected.length) }} دیدگاه انتخاب شده</div>
        <div class="flex gap-xs" style="flex-wrap:wrap">
          <button class="btn btn-sm btn-secondary" @click="moderate(selected, 'approve')">تأیید</button>
          <button class="btn btn-sm btn-secondary" @click="moderate(selected, 'pending')">در انتظار</button>
          <button class="btn btn-sm btn-secondary" @click="moderate(selected, 'spam')">هرزنامه</button>
          <button class="btn btn-sm btn-danger" @click="askDelete(selected)">حذف</button>
          <button class="btn btn-sm btn-ghost" @click="selected = []">لغو انتخاب</button>
        </div>
      </div>

      <div class="card">
        <div class="card-header" v-if="comments.length">
          <Checkbox :model-value="allSelected" @update:model-value="toggleAll" label="انتخاب همه" />
        </div>

        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!comments.length" icon="comment"
                    :title="search ? 'دیدگاهی یافت نشد' : 'دیدگاهی وجود ندارد'"
                    :text="search ? 'عبارت جستجو را تغییر دهید.' : 'وقتی بازدیدکنندگان دیدگاه بگذارند، اینجا نمایش داده می‌شود.'" />

        <template v-else>
          <div>
            <div v-for="comment in comments" :key="comment.id" class="comment-row">
              <Checkbox :model-value="selected.includes(comment.id)"
                        @update:model-value="toggleOne(comment.id)" />

              <div class="comment-main">
                <div class="comment-head">
                  <span class="comment-author">{{ comment.author_name }}</span>
                  <span class="comment-email">{{ comment.author_email }}</span>
                  <span class="badge" :class="statusTone(comment.status)">{{ comment.status_label }}</span>
                  <span class="tiny faint">{{ comment.date_relative }}</span>
                </div>

                <div class="comment-context">
                  در پاسخ به
                  <strong>{{ comment.post_title || 'نوشته حذف‌شده' }}</strong>
                  <span v-if="comment.parent_id"> — پاسخ به یک دیدگاه دیگر</span>
                </div>

                <div class="comment-text">{{ comment.content }}</div>

                <div class="comment-actions">
                  <button class="btn btn-sm btn-ghost" v-if="comment.status !== 'approved'"
                          @click="moderate([comment.id], 'approve')">
                    <Icon name="check" :size="14" /> تأیید
                  </button>
                  <button class="btn btn-sm btn-ghost" v-if="comment.status === 'approved'"
                          @click="moderate([comment.id], 'pending')">
                    <Icon name="clock" :size="14" /> لغو تأیید
                  </button>
                  <button class="btn btn-sm btn-ghost" @click="openEdit(comment)">
                    <Icon name="edit" :size="14" /> ویرایش
                  </button>
                  <button class="btn btn-sm btn-ghost" v-if="comment.status !== 'spam'"
                          @click="moderate([comment.id], 'spam')">
                    <Icon name="spam" :size="14" /> هرزنامه
                  </button>
                  <button class="btn btn-sm btn-ghost" @click="askDelete([comment.id])">
                    <Icon name="trash" :size="14" /> حذف
                  </button>
                </div>
              </div>
            </div>
          </div>

          <Pagination :meta="meta" @change="load" />
        </template>
      </div>

      <!-- ویرایش متن دیدگاه -->
      <Modal v-if="editing" title="ویرایش دیدگاه" :busy="busy" @close="editing = null">
        <div class="field">
          <label class="field-label">نویسنده</label>
          <input :value="editing.author_name + ' — ' + editing.author_email" type="text"
                 class="input" disabled>
        </div>
        <div class="field mb-0">
          <label class="field-label">متن دیدگاه</label>
          <textarea v-model="editText" class="textarea" rows="6" maxlength="3000"></textarea>
          <div class="field-hint">{{ fa(editText.length) }} از ۳٬۰۰۰ کاراکتر</div>
        </div>
        <template #footer>
          <button class="btn btn-ghost" @click="editing = null" :disabled="busy">انصراف</button>
          <button class="btn btn-primary" @click="saveEdit" :disabled="busy">
            <span v-if="busy" class="spinner"></span> ذخیره
          </button>
        </template>
      </Modal>

      <ConfirmDialog v-if="confirmAction"
                     :message="confirmAction.message"
                     confirm-label="حذف"
                     danger
                     :busy="busy"
                     @confirm="runConfirm"
                     @cancel="confirmAction = null" />
    </div>
  `,
};
