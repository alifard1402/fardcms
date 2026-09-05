/**
 * انتخابگر رسانه
 *
 * یک پنجره برای مرور، آپلود و انتخاب فایل از کتابخانه رسانه.
 * هم برای انتخاب تصویر شاخص و هم برای درج تصویر در ویرایشگر استفاده می‌شود.
 */

import { Icon } from '../icons.js';
import { Modal, Pagination, EmptyState, LoadingBlock, SearchInput } from './ui.js';
import { api } from '../api.js';
import { notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

/** آیکون مناسب برای هر نوع فایل */
export function mediaIcon(item) {
  const mime = item.mime_type || '';

  if (mime.startsWith('image/')) return 'image';
  if (mime.startsWith('video/')) return 'video';
  if (mime.startsWith('audio/')) return 'audio';

  return 'file';
}

export const MediaPicker = {
  name: 'MediaPicker',
  components: { Icon, Modal, Pagination, EmptyState, LoadingBlock, SearchInput },
  props: {
    multiple: { type: Boolean, default: false },
    imagesOnly: { type: Boolean, default: false },
    // فیلتر دلخواه نوع رسانه: image | video | audio | other
    // افزونه‌ها از این استفاده می‌کنند؛ imagesOnly میان‌بر همان image است
    type: { type: String, default: '' },
    title: { type: String, default: 'کتابخانه رسانه' },
  },
  emits: ['select', 'close'],
  setup(props, { emit }) {
    const items = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 24, total_pages: 0 });
    const loading = ref(true);
    const uploading = ref(false);
    const dragging = ref(false);
    const search = ref('');
    const selected = ref([]);
    const fileInput = ref(null);

    const load = async (page = 1) => {
      loading.value = true;

      try {
        const data = await api.media.list({
          page,
          per_page: 24,
          search: search.value,
          type: props.type || (props.imagesOnly ? 'image' : ''),
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

    const isSelected = (item) => selected.value.some((s) => s.id === item.id);

    const toggle = (item) => {
      if (!props.multiple) {
        selected.value = [item];
        return;
      }

      const index = selected.value.findIndex((s) => s.id === item.id);

      if (index === -1) {
        selected.value.push(item);
      } else {
        selected.value.splice(index, 1);
      }
    };

    /** انتخاب با دوبار کلیک، بدون نیاز به دکمه تأیید */
    const confirmOne = (item) => {
      selected.value = [item];
      emit('select', [item]);
    };

    const confirm = () => {
      if (!selected.value.length) {
        notify('هیچ فایلی انتخاب نشده است', 'error');
        return;
      }

      emit('select', selected.value);
    };

    /* ─── آپلود ────────────────────────────────────────────── */
    const uploadFiles = async (files) => {
      if (!files || !files.length) return;

      uploading.value = true;

      try {
        const data = await api.media.upload(files);

        notify(`${data.media.length.toLocaleString('fa-IR')} فایل آپلود شد`);

        // فایل‌های تازه در ابتدای فهرست نمایش داده می‌شوند
        items.value = [...data.media, ...items.value];
        meta.value.total += data.media.length;

        (data.errors || []).forEach((message) => notify(message, 'error'));
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

    return {
      items, meta, loading, uploading, dragging, search, selected, fileInput,
      load, isSelected, toggle, confirm, confirmOne, uploadFiles, onDrop, mediaIcon,
    };
  },
  template: `
    <Modal :title="title" size="lg" :busy="uploading" @close="$emit('close')">
      <div class="toolbar">
        <SearchInput v-model="search" placeholder="جستجوی فایل…" @search="load(1)" />
        <div class="toolbar-spacer"></div>
        <button class="btn btn-secondary btn-sm" @click="fileInput.click()" :disabled="uploading">
          <span v-if="uploading" class="spinner dark"></span>
          <Icon v-else name="upload" :size="15" />
          آپلود فایل
        </button>
        <input ref="fileInput" type="file" multiple class="hidden"
               :accept="imagesOnly ? 'image/*' : (type ? type + '/*' : undefined)"
               @change="uploadFiles($event.target.files)">
      </div>

      <div :class="['dropzone', { dragging }]"
           style="padding:18px;margin-bottom:16px"
           @click="fileInput.click()"
           @dragover.prevent="dragging = true"
           @dragleave.prevent="dragging = false"
           @drop.prevent="onDrop">
        <Icon name="upload" :size="24" style="margin-bottom:6px" />
        <div class="small muted">فایل‌ها را اینجا بکشید یا کلیک کنید</div>
      </div>

      <LoadingBlock v-if="loading" />

      <EmptyState v-else-if="!items.length" icon="media" title="فایلی یافت نشد"
                  text="اولین فایل خود را با کشیدن به کادر بالا آپلود کنید." />

      <template v-else>
        <div class="media-grid">
          <div v-for="item in items" :key="item.id"
               :class="['media-tile', { selected: isSelected(item) }]"
               @click="toggle(item)" @dblclick="confirmOne(item)"
               :title="item.original_name">
            <img v-if="item.is_image" :src="item.thumbnail_url" :alt="item.alt_text || item.original_name" loading="lazy">
            <div v-else class="media-file">
              <Icon :name="mediaIcon(item)" :size="32" />
              <span class="slug-text">{{ item.original_name }}</span>
            </div>
            <div class="media-check" v-if="isSelected(item)"><Icon name="check" :size="12" /></div>
            <div class="media-overlay">{{ item.size_label }}</div>
          </div>
        </div>

        <Pagination :meta="meta" @change="load" />
      </template>

      <template #footer>
        <span class="small muted grow" v-if="selected.length">
          {{ selected.length.toLocaleString('fa-IR') }} فایل انتخاب شده
        </span>
        <button class="btn btn-ghost" @click="$emit('close')">انصراف</button>
        <button class="btn btn-primary" @click="confirm" :disabled="!selected.length">
          {{ multiple ? 'درج فایل‌ها' : 'انتخاب فایل' }}
        </button>
      </template>
    </Modal>
  `,
};

/**
 * انتخابگر تصویر شاخص
 *
 * یک کادر کوچک با پیش‌نمایش تصویر که در نوار کنارِ فرم نوشته استفاده می‌شود.
 */
export const FeaturedImagePicker = {
  name: 'FeaturedImagePicker',
  components: { Icon, MediaPicker },
  props: {
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const showPicker = ref(false);

    const onSelect = (items) => {
      showPicker.value = false;

      if (items.length) {
        emit('update:modelValue', items[0].url);
      }
    };

    return { showPicker, onSelect };
  },
  template: `
    <div>
      <div v-if="modelValue" class="image-preview">
        <img :src="modelValue" alt="تصویر شاخص">
        <div class="image-preview-actions">
          <button type="button" @click="showPicker = true" title="تغییر تصویر">
            <Icon name="edit" :size="14" />
          </button>
          <button type="button" @click="$emit('update:modelValue', '')" title="حذف تصویر">
            <Icon name="trash" :size="14" />
          </button>
        </div>
      </div>

      <div v-else class="dropzone" style="padding:26px 16px" @click="showPicker = true">
        <Icon name="image" :size="28" style="margin-bottom:8px" />
        <div class="small muted">انتخاب تصویر شاخص</div>
      </div>

      <MediaPicker v-if="showPicker" images-only title="انتخاب تصویر شاخص"
                   @select="onSelect" @close="showPicker = false" />
    </div>
  `,
};
