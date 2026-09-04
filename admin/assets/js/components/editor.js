/**
 * ویرایشگر محتوا
 *
 * یک ویرایشگر بصری بر پایه contentEditable با امکان جابه‌جایی به حالت
 * ویرایش HTML. عمداً هیچ کتابخانه بیرونی استفاده نشده تا پنل بدون
 * وابستگی و مرحله build کار کند.
 */

import { Icon } from '../icons.js';
import { Modal } from './ui.js';
import { MediaPicker } from './media-picker.js';
import { notify } from '../store.js';

const { ref, computed, watch, onMounted, nextTick } = Vue;

export const ContentEditor = {
  name: 'ContentEditor',
  components: { Icon, Modal, MediaPicker },
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'محتوای خود را بنویسید…' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const area = ref(null);
    const mode = ref('visual');            // visual یا source
    const source = ref(props.modelValue);
    const showLinkModal = ref(false);
    const showMediaPicker = ref(false);
    const linkForm = ref({ url: '', text: '', newTab: false });
    const activeMarks = ref({});

    /**
     * همگام‌سازی محتوای ویرایشگر با مقدار بیرونی
     *
     * فقط زمانی نوشته می‌شود که مقدار واقعاً متفاوت باشد؛ در غیر این صورت
     * نوشتن دوباره‌ی innerHTML مکان نشانگر (caret) را از بین می‌برد.
     */
    watch(() => props.modelValue, (value) => {
      if (mode.value === 'source') {
        if (value !== source.value) source.value = value;
        return;
      }

      if (area.value && area.value.innerHTML !== value) {
        area.value.innerHTML = value || '';
      }
    });

    onMounted(() => {
      if (area.value) {
        area.value.innerHTML = props.modelValue || '';
      }
    });

    /** انتشار تغییرات از حالت بصری */
    const emitFromVisual = () => {
      if (area.value) {
        emit('update:modelValue', area.value.innerHTML);
      }
    };

    /** انتشار تغییرات از حالت HTML */
    const emitFromSource = () => emit('update:modelValue', source.value);

    /**
     * اجرای یک فرمان قالب‌بندی روی متن انتخاب‌شده
     *
     * execCommand منسوخ اعلام شده اما تنها راه بدون وابستگی است که در
     * همه مرورگرهای امروزی کار می‌کند.
     */
    const exec = (command, value = null) => {
      if (mode.value !== 'visual') return;

      area.value?.focus();
      document.execCommand(command, false, value);
      emitFromVisual();
      refreshMarks();
    };

    /** به‌روزرسانی وضعیت فعال دکمه‌های نوار ابزار */
    const refreshMarks = () => {
      if (mode.value !== 'visual') return;

      const marks = {};

      for (const command of ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList']) {
        try {
          marks[command] = document.queryCommandState(command);
        } catch {
          marks[command] = false;
        }
      }

      activeMarks.value = marks;
    };

    /** تغییر بلوک جاری (عنوان، پاراگراف، نقل‌قول، کد) */
    const setBlock = (tag) => exec('formatBlock', `<${tag}>`);

    /** جابه‌جایی بین حالت بصری و HTML */
    const toggleMode = () => {
      if (mode.value === 'visual') {
        source.value = area.value?.innerHTML || '';
        mode.value = 'source';
      } else {
        mode.value = 'source-to-visual';
        emit('update:modelValue', source.value);

        // پس از رندر دوباره ناحیه، محتوا نوشته می‌شود
        nextTick(() => {
          mode.value = 'visual';
          nextTick(() => {
            if (area.value) area.value.innerHTML = source.value;
          });
        });
      }
    };

    /* ─── درج پیوند ────────────────────────────────────────── */
    const openLinkModal = () => {
      const selection = window.getSelection();
      linkForm.value = {
        url: '',
        text: selection && !selection.isCollapsed ? selection.toString() : '',
        newTab: false,
      };
      showLinkModal.value = true;
    };

    const insertLink = () => {
      let { url, text, newTab } = linkForm.value;
      url = url.trim();

      if (url === '') {
        notify('آدرس پیوند را وارد کنید', 'error');
        return;
      }

      // پروتکل‌های اجرایی هرگز درج نمی‌شوند
      if (/^\s*(javascript|data|vbscript):/i.test(url)) {
        notify('این نوع آدرس مجاز نیست', 'error');
        return;
      }

      // آدرس‌های بدون پروتکل و بدون اسلش ابتدایی به https تبدیل می‌شوند
      if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url)) {
        url = 'https://' + url;
      }

      const label = (text.trim() || url)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

      const attrs = newTab ? ' target="_blank" rel="noopener noreferrer"' : '';
      exec('insertHTML', `<a href="${url.replace(/"/g, '&quot;')}"${attrs}>${label}</a>`);

      showLinkModal.value = false;
    };

    /* ─── درج تصویر از کتابخانه رسانه ──────────────────────── */
    const onMediaSelect = (items) => {
      showMediaPicker.value = false;

      const html = items
        .map((item) => {
          const alt = (item.alt_text || item.original_name || '').replace(/"/g, '&quot;');

          return item.is_image
            ? `<img src="${item.url}" alt="${alt}">`
            : `<a href="${item.url}" target="_blank" rel="noopener">${alt}</a>`;
        })
        .join('');

      if (html) exec('insertHTML', html);
    };

    /**
     * چسباندن متن بدون قالب‌بندی مبدأ
     *
     * چسباندن مستقیم از Word یا وب، استایل‌های ناخواسته و تگ‌های
     * خطرناک وارد محتوا می‌کند.
     */
    const onPaste = (event) => {
      event.preventDefault();

      const text = event.clipboardData?.getData('text/plain') || '';
      document.execCommand('insertText', false, text);
      emitFromVisual();
    };

    /* ─── شمارش کلمات ──────────────────────────────────────── */
    const stats = computed(() => {
      const html = mode.value === 'source' ? source.value : props.modelValue;
      const text = html.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').trim();
      const words = text ? text.split(/\s+/).filter(Boolean).length : 0;

      return { words, chars: text.length };
    });

    return {
      area, mode, source, stats, activeMarks,
      showLinkModal, showMediaPicker, linkForm,
      exec, setBlock, toggleMode, refreshMarks,
      emitFromVisual, emitFromSource,
      openLinkModal, insertLink, onMediaSelect, onPaste,
    };
  },
  template: `
    <div class="editor">
      <div class="editor-toolbar">
        <template v-if="mode !== 'source'">
          <button type="button" class="editor-btn" :class="{ active: activeMarks.bold }"
                  @click="exec('bold')" title="درشت (Ctrl+B)">
            <Icon name="bold" :size="15" />
          </button>
          <button type="button" class="editor-btn" :class="{ active: activeMarks.italic }"
                  @click="exec('italic')" title="کج (Ctrl+I)">
            <Icon name="italic" :size="15" />
          </button>
          <button type="button" class="editor-btn" :class="{ active: activeMarks.underline }"
                  @click="exec('underline')" title="زیرخط (Ctrl+U)">
            <Icon name="underline" :size="15" />
          </button>

          <span class="editor-sep"></span>

          <button type="button" class="editor-btn" @click="setBlock('h2')" title="عنوان بزرگ">H2</button>
          <button type="button" class="editor-btn" @click="setBlock('h3')" title="عنوان متوسط">H3</button>
          <button type="button" class="editor-btn" @click="setBlock('p')" title="پاراگراف">P</button>

          <span class="editor-sep"></span>

          <button type="button" class="editor-btn" :class="{ active: activeMarks.insertUnorderedList }"
                  @click="exec('insertUnorderedList')" title="فهرست نقطه‌ای">
            <Icon name="list" :size="15" />
          </button>
          <button type="button" class="editor-btn" :class="{ active: activeMarks.insertOrderedList }"
                  @click="exec('insertOrderedList')" title="فهرست شماره‌دار">
            <Icon name="listOrdered" :size="15" />
          </button>
          <button type="button" class="editor-btn" @click="setBlock('blockquote')" title="نقل‌قول">
            <Icon name="quote" :size="15" />
          </button>
          <button type="button" class="editor-btn" @click="setBlock('pre')" title="بلوک کد">
            <Icon name="code" :size="15" />
          </button>

          <span class="editor-sep"></span>

          <button type="button" class="editor-btn" @click="openLinkModal" title="درج پیوند">
            <Icon name="link" :size="15" />
          </button>
          <button type="button" class="editor-btn" @click="showMediaPicker = true" title="درج تصویر">
            <Icon name="image" :size="15" />
          </button>
          <button type="button" class="editor-btn" @click="exec('insertHorizontalRule')" title="خط جداکننده">—</button>
          <button type="button" class="editor-btn" @click="exec('removeFormat')" title="حذف قالب‌بندی">
            <Icon name="x" :size="15" />
          </button>
        </template>

        <span class="toolbar-spacer" style="flex:1"></span>

        <button type="button" class="editor-btn" :class="{ active: mode === 'source' }"
                @click="toggleMode" :title="mode === 'source' ? 'حالت بصری' : 'ویرایش HTML'">
          <Icon name="code" :size="15" />
        </button>
      </div>

      <!-- حالت بصری -->
      <div v-if="mode === 'visual'"
           ref="area"
           class="editor-area"
           contenteditable="true"
           :data-placeholder="placeholder"
           dir="rtl"
           @input="emitFromVisual"
           @paste="onPaste"
           @keyup="refreshMarks"
           @mouseup="refreshMarks"
           @blur="emitFromVisual"></div>

      <!-- حالت HTML -->
      <textarea v-else-if="mode === 'source'"
                v-model="source"
                class="editor-source"
                spellcheck="false"
                @input="emitFromSource"
                :placeholder="placeholder"></textarea>

      <div class="editor-footer">
        <span>{{ stats.words.toLocaleString('fa-IR') }} کلمه · {{ stats.chars.toLocaleString('fa-IR') }} کاراکتر</span>
        <span>{{ mode === 'source' ? 'ویرایش HTML' : 'حالت بصری' }}</span>
      </div>

      <!-- پنجره درج پیوند -->
      <Modal v-if="showLinkModal" title="درج پیوند" size="sm" @close="showLinkModal = false">
        <div class="field">
          <label class="field-label">آدرس <span class="req">*</span></label>
          <input v-model="linkForm.url" type="text" class="input input-ltr"
                 placeholder="https://example.com" @keydown.enter="insertLink">
        </div>
        <div class="field">
          <label class="field-label">متن پیوند</label>
          <input v-model="linkForm.text" type="text" class="input" placeholder="متن نمایشی">
        </div>
        <label class="checkbox-row">
          <div :class="['checkbox', { checked: linkForm.newTab }]"
               @click="linkForm.newTab = !linkForm.newTab">
            <Icon name="check" :size="12" />
          </div>
          <span class="checkbox-text">باز شدن در پنجره جدید</span>
        </label>
        <template #footer>
          <button class="btn btn-ghost" @click="showLinkModal = false">انصراف</button>
          <button class="btn btn-primary" @click="insertLink">درج پیوند</button>
        </template>
      </Modal>

      <!-- انتخاب از کتابخانه رسانه -->
      <MediaPicker v-if="showMediaPicker" multiple
                   @select="onMediaSelect" @close="showMediaPicker = false" />
    </div>
  `,
};
