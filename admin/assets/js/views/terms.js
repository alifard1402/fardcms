/**
 * مدیریت دسته‌بندی‌ها و برچسب‌ها
 *
 * یک کامپوننت برای هر دو طبقه‌بندی؛ نوع از پارامتر taxonomy می‌آید.
 */

import { Icon } from '../icons.js';
import { Modal, EmptyState, LoadingBlock, SearchInput, ConfirmDialog } from '../components/ui.js';
import { api } from '../api.js';
import { notify, notifyError } from '../store.js';
import { navigate } from '../router.js';

const { ref, computed, watch, onMounted } = Vue;

export const TermsView = {
  name: 'TermsView',
  components: { Icon, Modal, EmptyState, LoadingBlock, SearchInput, ConfirmDialog },
  props: {
    taxonomy: { type: String, default: 'category' },
  },
  setup(props) {
    const terms = ref([]);
    const tree = ref([]);
    const loading = ref(true);
    const search = ref('');
    const editing = ref(null);
    const form = ref({ name: '', slug: '', description: '', parent_id: null });
    const confirmDelete = ref(null);
    const busy = ref(false);
    const errors = ref({});

    const isCategory = computed(() => props.taxonomy === 'category');
    const labels = computed(() => (isCategory.value
      ? { one: 'دسته', many: 'دسته‌بندی‌ها' }
      : { one: 'برچسب', many: 'برچسب‌ها' }));

    const load = async () => {
      loading.value = true;

      try {
        const data = await api.terms.list(props.taxonomy, { search: search.value });

        terms.value = data.terms || [];
        tree.value = data.tree || [];
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    // جابه‌جایی بین دسته و برچسب فهرست را دوباره می‌گیرد
    watch(() => props.taxonomy, () => {
      search.value = '';
      editing.value = null;
      load();
    });

    /**
     * تبدیل درخت به فهرست مسطح همراه با عمق، برای نمایش تودرتو در جدول
     */
    const flatTree = computed(() => {
      const rows = [];

      const walk = (nodes, depth) => {
        nodes.forEach((node) => {
          rows.push({ ...node, depth });

          if (node.children?.length) {
            walk(node.children, depth + 1);
          }
        });
      };

      walk(isCategory.value ? tree.value : terms.value, 0);

      return rows;
    });

    /** دسته‌هایی که می‌توانند والد انتخاب شوند (بدون خود مورد در حال ویرایش) */
    const parentChoices = computed(
      () => terms.value.filter((t) => !editing.value?.id || t.id !== editing.value.id)
    );

    /* ─── فرم ──────────────────────────────────────────────── */
    const openNew = () => {
      editing.value = { id: null };
      form.value = { name: '', slug: '', description: '', parent_id: null };
      errors.value = {};
    };

    const openEdit = (term) => {
      editing.value = term;
      form.value = {
        name: term.name,
        slug: term.slug,
        description: term.description || '',
        parent_id: term.parent_id,
      };
      errors.value = {};
    };

    const save = async () => {
      errors.value = {};

      if (!form.value.name.trim()) {
        errors.value.name = 'نام الزامی است';
        return;
      }

      busy.value = true;

      try {
        await api.terms.save({
          id: editing.value.id,
          taxonomy: props.taxonomy,
          ...form.value,
        });

        notify(editing.value.id ? 'تغییرات ذخیره شد' : `${labels.value.one} ایجاد شد`);
        editing.value = null;
        await load();
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const remove = async () => {
      busy.value = true;

      try {
        await api.terms.remove(confirmDelete.value.id);

        notify(`${labels.value.one} حذف شد`);
        confirmDelete.value = null;
        await load();
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    return {
      terms, tree, flatTree, parentChoices, loading, search, editing, form,
      confirmDelete, busy, errors, isCategory, labels,
      load, openNew, openEdit, save, remove, navigate,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <SearchInput v-model="search" :placeholder="'جستجو در ' + labels.many + '…'" @search="load" />
        <div class="toolbar-spacer"></div>
        <button class="btn btn-primary" @click="openNew">
          <Icon name="plus" :size="16" /> {{ labels.one }} جدید
        </button>
      </div>

      <div class="card">
        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!flatTree.length"
                    :icon="isCategory ? 'folder' : 'tag'"
                    :title="search ? 'نتیجه‌ای یافت نشد' : 'هنوز ' + labels.one + 'ای ندارید'"
                    :text="isCategory
                      ? 'دسته‌بندی‌ها به سازمان‌دهی نوشته‌ها کمک می‌کنند و می‌توانند زیرمجموعه داشته باشند.'
                      : 'برچسب‌ها موضوعات جزئی‌تر نوشته‌ها را مشخص می‌کنند.'">
          <button class="btn btn-primary" @click="openNew">
            <Icon name="plus" :size="16" /> {{ labels.one }} جدید
          </button>
        </EmptyState>

        <div class="table-wrap" v-else>
          <table class="table">
            <thead>
              <tr>
                <th>نام</th>
                <th>نامک</th>
                <th>توضیح</th>
                <th class="nowrap">تعداد نوشته</th>
                <th class="col-actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="term in flatTree" :key="term.id">
                <td>
                  <div class="flex gap-xs" :style="{ paddingInlineStart: (term.depth * 22) + 'px' }">
                    <Icon v-if="term.depth > 0" name="chevronLeft" :size="13" class="faint" />
                    <span class="cell-title">{{ term.name }}</span>
                  </div>
                </td>
                <td><span class="slug-text faint">{{ term.slug }}</span></td>
                <td class="muted small truncate" style="max-width:280px">{{ term.description || '—' }}</td>
                <td>
                  <button class="badge badge-muted" @click="navigate('/posts?term=' + term.id)"
                          :title="'نمایش نوشته‌های این ' + labels.one">
                    {{ fa(term.count) }}
                  </button>
                </td>
                <td class="col-actions">
                  <div class="row-actions">
                    <a class="icon-btn" :href="term.url" target="_blank" rel="noopener" title="مشاهده">
                      <Icon name="eye" :size="16" />
                    </a>
                    <button class="icon-btn" @click="openEdit(term)" title="ویرایش">
                      <Icon name="edit" :size="16" />
                    </button>
                    <button class="icon-btn" @click="confirmDelete = term" title="حذف">
                      <Icon name="trash" :size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- فرم ایجاد و ویرایش -->
      <Modal v-if="editing"
             :title="(editing.id ? 'ویرایش ' : 'افزودن ') + labels.one"
             :busy="busy"
             @close="editing = null">
        <div class="field">
          <label class="field-label">نام <span class="req">*</span></label>
          <input v-model="form.name" type="text" :class="['input', { 'has-error': errors.name }]"
                 :placeholder="'نام ' + labels.one" @keydown.enter="save">
          <div class="field-error" v-if="errors.name">{{ errors.name }}</div>
        </div>

        <div class="field">
          <label class="field-label">نامک</label>
          <input v-model="form.slug" type="text" class="input input-auto" placeholder="my-category">
          <div class="field-hint">اگر خالی بماند، از نام ساخته می‌شود.</div>
        </div>

        <div class="field" v-if="isCategory">
          <label class="field-label">دسته والد</label>
          <select v-model="form.parent_id" class="select">
            <option :value="null">— بدون والد —</option>
            <option v-for="choice in parentChoices" :key="choice.id" :value="choice.id">
              {{ choice.name }}
            </option>
          </select>
        </div>

        <div class="field mb-0">
          <label class="field-label">توضیح</label>
          <textarea v-model="form.description" class="textarea" rows="3" maxlength="500"
                    placeholder="توضیح کوتاهی که در صفحه آرشیو نمایش داده می‌شود."></textarea>
        </div>

        <template #footer>
          <button class="btn btn-ghost" @click="editing = null" :disabled="busy">انصراف</button>
          <button class="btn btn-primary" @click="save" :disabled="busy">
            <span v-if="busy" class="spinner"></span>
            {{ editing.id ? 'ذخیره تغییرات' : 'افزودن' }}
          </button>
        </template>
      </Modal>

      <ConfirmDialog v-if="confirmDelete"
                     :message="'«' + confirmDelete.name + '» حذف می‌شود. نوشته‌ها حذف نمی‌شوند اما ارتباطشان با این ' + labels.one + ' از بین می‌رود.'"
                     confirm-label="حذف"
                     danger
                     :busy="busy"
                     @confirm="remove"
                     @cancel="confirmDelete = null" />
    </div>
  `,
};
