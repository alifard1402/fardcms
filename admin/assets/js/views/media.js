/**
 * کتابخانه رسانه — آپلود، مرور و ویرایش فایل‌ها
 */

import { Icon } from '../icons.js';
import {
  Modal, Pagination, EmptyState, LoadingBlock, SearchInput, ConfirmDialog,
} from '../components/ui.js';
import { mediaIcon } from '../components/media-picker.js';
import { api } from '../api.js';
import { notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

export const MediaView = {
  name: 'MediaView',
  components: { Icon, Modal, Pagination, EmptyState, LoadingBlock, SearchInput, ConfirmDialog },
  setup() {
    const items = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 24, total_pages: 0 });
    const loading = ref(true);
    const uploading = ref(false);
    const dragging = ref(false);
    const search = ref('');
    const typeFilter = ref('');
    const detail = ref(null);
    const detailForm = ref({ alt_text: '', caption: '' });
    const confirmDelete = ref(null);
    const busy = ref(false);
    const fileInput = ref(null);
    const copied = ref(false);

    const load = async (page = 1) => {
      loading.value = true;

      try {
        const data = await api.media.list({
          page,
          per_page: 24,
          search: search.value,
          type: typeFilter.value,
        });

        items.value = data.media || [];
        meta.value = data.meta || meta.value;
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => load());

    /* ─── آپلود ────────────────────────────────────────────── */
    const uploadFiles = async (files) => {
      if (!files || !files.length) return;

      uploading.value = true;

      try {
        const data = await api.media.upload(files);

        notify(`${data.media.length.toLocaleString('fa-IR')} فایل آپلود شد`);
        (data.errors || []).forEach((message) => notify(message, 'error'));

        await load(1);
      } catch (error) {
        notifyError(error);
      } finally {
        uploading.value = false;

        if (fileInput.value) fileInput.value.value = '';
      }
    };

    const onDrop = (event) => {
      dragging.value = false;
      uploadFiles(event.dataTransfer?.files);
    };

    /* ─── جزئیات فایل ──────────────────────────────────────── */
    const openDetail = (item) => {
      detail.value = item;
      detailForm.value = { alt_text: item.alt_text || '', caption: item.caption || '' };
      copied.value = false;
    };

    const saveDetail = async () => {
      busy.value = true;

      try {
        const data = await api.media.update({ id: detail.value.id, ...detailForm.value });

        // رکورد در فهرست هم به‌روزرسانی می‌شود تا بارگذاری دوباره لازم نباشد
        const index = items.value.findIndex((i) => i.id === detail.value.id);
        if (index !== -1) items.value[index] = data.media;

        notify('تغییرات ذخیره شد');
        detail.value = null;
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const removeItem = async () => {
      busy.value = true;

      try {
        await api.media.remove(confirmDelete.value.id);
        notify('فایل حذف شد');

        confirmDelete.value = null;
        detail.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    /** کپی آدرس فایل در حافظه سیستم */
    const copyUrl = async (url) => {
      try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        notify('آدرس فایل کپی شد');

        setTimeout(() => { copied.value = false; }, 2000);
      } catch {
        notify('کپی خودکار ممکن نشد؛ آدرس را دستی انتخاب کنید', 'error');
      }
    };

    const typeFilters = [
      { value: '', label: 'همه' },
      { value: 'image', label: 'تصاویر' },
      { value: 'video', label: 'ویدیو' },
      { value: 'audio', label: 'صدا' },
      { value: 'document', label: 'اسناد' },
    ];

    const setType = (value) => {
      typeFilter.value = value;
      load(1);
    };

    return {
      items, meta, loading, uploading, dragging, search, typeFilter, typeFilters,
      detail, detailForm, confirmDelete, busy, fileInput, copied,
      load, uploadFiles, onDrop, openDetail, saveDetail, removeItem, copyUrl, setType, mediaIcon,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <SearchInput v-model="search" placeholder="جستجوی فایل…" @search="load(1)" />

        <div class="status-filters">
          <button v-for="filter in typeFilters" :key="filter.value"
                  :class="['status-filter', { active: typeFilter === filter.value }]"
                  @click="setType(filter.value)">{{ filter.label }}</button>
        </div>

        <div class="toolbar-spacer"></div>

        <button class="btn btn-primary" @click="fileInput.click()" :disabled="uploading">
          <span v-if="uploading" class="spinner"></span>
          <Icon v-else name="upload" :size="16" />
          آپلود فایل
        </button>
        <input ref="fileInput" type="file" multiple class="hidden"
               @change="uploadFiles($event.target.files)">
      </div>

      <div :class="['dropzone', { dragging }]" class="mb-lg"
           @click="fileInput.click()"
           @dragover.prevent="dragging = true"
           @dragleave.prevent="dragging = false"
           @drop.prevent="onDrop">
        <Icon name="upload" :size="34" />
        <div class="empty-title" style="font-size:14px">فایل‌ها را اینجا رها کنید</div>
        <p class="small faint mb-0">یا کلیک کنید تا از سیستم انتخاب کنید</p>
      </div>

      <div class="card">
        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!items.length" icon="media"
                    :title="search ? 'فایلی یافت نشد' : 'کتابخانه خالی است'"
                    :text="search ? 'عبارت جستجو را تغییر دهید.' : 'اولین فایل خود را آپلود کنید.'" />

        <template v-else>
          <div class="card-body">
            <div class="media-grid">
              <div v-for="item in items" :key="item.id" class="media-tile"
                   @click="openDetail(item)" :title="item.original_name">
                <img v-if="item.is_image" :src="item.thumbnail_url"
                     :alt="item.alt_text || item.original_name" loading="lazy">
                <div v-else class="media-file">
                  <Icon :name="mediaIcon(item)" :size="32" />
                  <span class="slug-text">{{ item.original_name }}</span>
                </div>
                <div class="media-overlay">{{ item.size_label }}</div>
              </div>
            </div>
          </div>

          <Pagination :meta="meta" @change="load" />
        </template>
      </div>

      <!-- جزئیات فایل -->
      <Modal v-if="detail" title="جزئیات فایل" size="lg" :busy="busy" @close="detail = null">
        <div class="split-narrow" style="gap:22px">
          <div>
            <div class="image-preview" v-if="detail.is_image">
              <img :src="detail.url" :alt="detail.alt_text" style="aspect-ratio:auto;max-height:420px;object-fit:contain">
            </div>
            <div class="dropzone" style="cursor:default" v-else>
              <Icon :name="mediaIcon(detail)" :size="44" />
              <div class="small muted mt">{{ detail.original_name }}</div>
              <a class="btn btn-secondary btn-sm mt" :href="detail.url" target="_blank" rel="noopener">
                <Icon name="external" :size="14" /> باز کردن فایل
              </a>
            </div>

            <div class="field mt-lg mb-0">
              <label class="field-label">متن جایگزین (alt)</label>
              <input v-model="detailForm.alt_text" type="text" class="input" maxlength="255"
                     placeholder="توضیح کوتاه تصویر برای موتورهای جستجو و صفحه‌خوان‌ها">
            </div>

            <div class="field mt mb-0">
              <label class="field-label">توضیح</label>
              <textarea v-model="detailForm.caption" class="textarea" rows="2" maxlength="500"></textarea>
            </div>
          </div>

          <div>
            <div class="card" style="box-shadow:none">
              <div class="card-body">
                <div class="field">
                  <label class="field-label">آدرس فایل</label>
                  <input :value="detail.url" type="text" class="input input-auto" readonly
                         @focus="$event.target.select()">
                  <button class="btn btn-sm btn-secondary btn-block mt" @click="copyUrl(detail.url)">
                    <Icon :name="copied ? 'check' : 'copy'" :size="14" />
                    {{ copied ? 'کپی شد' : 'کپی آدرس' }}
                  </button>
                </div>

                <div class="divider"></div>

                <div class="flex" style="justify-content:space-between;margin-bottom:7px">
                  <span class="small muted">نام اصلی</span>
                  <span class="small truncate slug-text" style="max-width:150px">{{ detail.original_name }}</span>
                </div>
                <div class="flex" style="justify-content:space-between;margin-bottom:7px">
                  <span class="small muted">نوع</span>
                  <span class="small mono">{{ detail.mime_type }}</span>
                </div>
                <div class="flex" style="justify-content:space-between;margin-bottom:7px">
                  <span class="small muted">حجم</span>
                  <span class="small">{{ detail.size_label }}</span>
                </div>
                <div class="flex" style="justify-content:space-between;margin-bottom:7px" v-if="detail.width">
                  <span class="small muted">ابعاد</span>
                  <span class="small mono">{{ detail.width }} × {{ detail.height }}</span>
                </div>
                <div class="flex" style="justify-content:space-between;margin-bottom:7px">
                  <span class="small muted">آپلودکننده</span>
                  <span class="small">{{ detail.user_name || '—' }}</span>
                </div>
                <div class="flex" style="justify-content:space-between">
                  <span class="small muted">تاریخ</span>
                  <span class="small">{{ detail.date_jalali }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <template #footer>
          <button class="btn btn-danger" @click="confirmDelete = detail" :disabled="busy">
            <Icon name="trash" :size="15" /> حذف فایل
          </button>
          <div class="grow"></div>
          <button class="btn btn-ghost" @click="detail = null" :disabled="busy">بستن</button>
          <button class="btn btn-primary" @click="saveDetail" :disabled="busy">
            <span v-if="busy" class="spinner"></span>
            ذخیره
          </button>
        </template>
      </Modal>

      <ConfirmDialog v-if="confirmDelete"
                     :message="'فایل «' + confirmDelete.original_name + '» برای همیشه حذف می‌شود. اگر در محتوایی استفاده شده باشد، آن تصویر از بین می‌رود.'"
                     confirm-label="حذف فایل"
                     danger
                     :busy="busy"
                     @confirm="removeItem"
                     @cancel="confirmDelete = null" />
    </div>
  `,
};
