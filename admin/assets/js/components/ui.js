/**
 * کامپوننت‌های رابط کاربری مشترک
 */

import { Icon } from '../icons.js';
import { store, dismissToast } from '../store.js';

const { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } = Vue;

/* ─── پنجره مودال ─────────────────────────────────────────── */
/**
 * پنجره مودال
 *
 * محتوا با Teleport به body منتقل می‌شود و نه جایی که در قالب نوشته شده.
 * دلیلش: کارت‌ها در پوسته تیره backdrop-filter دارند و هر عنصری که
 * backdrop-filter داشته باشد برای فرزندان position:fixed خود «قاب مرجع»
 * می‌سازد. مودالی که داخل یک کارت رندر می‌شد، به‌جای پوشاندن کل صفحه،
 * داخل همان کارت می‌نشست و overflow:hidden کارت هم می‌بریدش — روی موبایل
 * کاملاً غیرقابل استفاده می‌شد.
 */
export const Modal = {
  name: 'Modal',
  components: { Icon },
  props: {
    title: { type: String, default: '' },
    size: { type: String, default: '' },       // sm | lg
    busy: { type: Boolean, default: false },
  },
  emits: ['close'],
  setup(props, { emit }) {
    // بستن با کلید Escape
    const onKeydown = (event) => {
      if (event.key === 'Escape' && !props.busy) {
        emit('close');
      }
    };

    onMounted(() => {
      document.addEventListener('keydown', onKeydown);
      document.body.style.overflow = 'hidden';
    });

    onBeforeUnmount(() => {
      document.removeEventListener('keydown', onKeydown);
      document.body.style.overflow = '';
    });

    return { onKeydown };
  },
  template: `
    <Teleport to="body">
    <div class="modal-backdrop" @click.self="!busy && $emit('close')" role="dialog" aria-modal="true">
      <div class="modal" :class="size ? 'modal-' + size : ''">
        <div class="modal-header">
          <h3 class="modal-title">{{ title }}</h3>
          <button class="icon-btn" @click="$emit('close')" :disabled="busy" aria-label="بستن">
            <Icon name="x" :size="18" />
          </button>
        </div>
        <div class="modal-body"><slot /></div>
        <div class="modal-footer" v-if="$slots.footer"><slot name="footer" /></div>
      </div>
    </div>
    </Teleport>
  `,
};

/* ─── پنجره تأیید ─────────────────────────────────────────── */
export const ConfirmDialog = {
  name: 'ConfirmDialog',
  components: { Modal, Icon },
  props: {
    title: { type: String, default: 'تأیید عملیات' },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: 'تأیید' },
    danger: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ['confirm', 'cancel'],
  template: `
    <Modal :title="title" size="sm" :busy="busy" @close="$emit('cancel')">
      <div class="flex gap" style="align-items:flex-start">
        <div class="stat-icon" :class="danger ? 'danger' : 'warning'" style="margin-bottom:0">
          <Icon name="alert" :size="18" />
        </div>
        <p class="grow" style="line-height:1.9">{{ message }}</p>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="$emit('cancel')" :disabled="busy">انصراف</button>
        <button :class="['btn', danger ? 'btn-danger' : 'btn-primary']"
                @click="$emit('confirm')" :disabled="busy">
          <span v-if="busy" class="spinner"></span>
          {{ confirmLabel }}
        </button>
      </template>
    </Modal>
  `,
};

/* ─── پیام‌های شناور ──────────────────────────────────────── */
export const ToastStack = {
  name: 'ToastStack',
  components: { Icon },
  setup() {
    const iconFor = (type) => ({ success: 'check', error: 'x', info: 'info' }[type] || 'info');

    return { store, dismissToast, iconFor };
  },
  template: `
    <div class="toast-stack" aria-live="polite">
      <div v-for="toast in store.toasts" :key="toast.id"
           :class="['toast', toast.type]" @click="dismissToast(toast.id)">
        <div class="toast-icon"><Icon :name="iconFor(toast.type)" :size="12" /></div>
        <div class="toast-message">{{ toast.message }}</div>
      </div>
    </div>
  `,
};

/* ─── صفحه‌بندی ───────────────────────────────────────────── */
export const Pagination = {
  name: 'Pagination',
  components: { Icon },
  props: {
    meta: { type: Object, required: true },   // { total, page, per_page, total_pages }
  },
  emits: ['change'],
  setup(props) {
    /**
     * شماره صفحه‌های نمایشی با «…» برای فاصله‌های بزرگ
     */
    const pages = computed(() => {
      const total = props.meta.total_pages || 0;
      const current = props.meta.page || 1;

      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }

      const result = [1];
      const from = Math.max(2, current - 1);
      const to = Math.min(total - 1, current + 1);

      if (from > 2) result.push('…');
      for (let i = from; i <= to; i++) result.push(i);
      if (to < total - 1) result.push('…');
      result.push(total);

      return result;
    });

    const range = computed(() => {
      const { page = 1, per_page: perPage = 20, total = 0 } = props.meta;
      const first = total === 0 ? 0 : (page - 1) * perPage + 1;
      const last = Math.min(page * perPage, total);

      return { first, last, total };
    });

    return { pages, range };
  },
  template: `
    <div class="pagination" v-if="meta.total > 0">
      <div class="pagination-info">
        نمایش {{ range.first.toLocaleString('fa-IR') }} تا {{ range.last.toLocaleString('fa-IR') }}
        از {{ range.total.toLocaleString('fa-IR') }} مورد
      </div>
      <div class="pagination-pages" v-if="meta.total_pages > 1">
        <button class="page-btn" :disabled="meta.page <= 1" @click="$emit('change', meta.page - 1)"
                aria-label="صفحه قبل">
          <Icon name="chevronRight" :size="15" />
        </button>
        <template v-for="(p, i) in pages" :key="i">
          <span v-if="p === '…'" class="page-btn" style="cursor:default">…</span>
          <button v-else :class="['page-btn', { active: p === meta.page }]" @click="$emit('change', p)">
            {{ p.toLocaleString('fa-IR') }}
          </button>
        </template>
        <button class="page-btn" :disabled="meta.page >= meta.total_pages"
                @click="$emit('change', meta.page + 1)" aria-label="صفحه بعد">
          <Icon name="chevronLeft" :size="15" />
        </button>
      </div>
    </div>
  `,
};

/* ─── وضعیت خالی ──────────────────────────────────────────── */
export const EmptyState = {
  name: 'EmptyState',
  components: { Icon },
  props: {
    icon: { type: String, default: 'info' },
    title: { type: String, required: true },
    text: { type: String, default: '' },
  },
  template: `
    <div class="empty-state">
      <div class="empty-icon"><Icon :name="icon" :size="29" /></div>
      <div class="empty-title">{{ title }}</div>
      <p class="empty-text" v-if="text">{{ text }}</p>
      <slot />
    </div>
  `,
};

/* ─── جعبه بارگذاری ───────────────────────────────────────── */
export const LoadingBlock = {
  name: 'LoadingBlock',
  template: `<div class="loading-block"><div class="spinner dark lg"></div></div>`,
};

/* ─── کلید تغییر وضعیت ────────────────────────────────────── */
export const Switch = {
  name: 'Switch',
  props: {
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  template: `
    <label class="checkbox-row" style="align-items:center">
      <div :class="['switch', { on: modelValue }]" @click="$emit('update:modelValue', !modelValue)"
           role="switch" :aria-checked="modelValue" tabindex="0"
           @keydown.space.prevent="$emit('update:modelValue', !modelValue)"></div>
      <div class="grow" v-if="label || hint">
        <div class="checkbox-text">{{ label }}</div>
        <div class="field-hint" v-if="hint" style="margin-top:1px">{{ hint }}</div>
      </div>
    </label>
  `,
};

/* ─── جعبه انتخاب ─────────────────────────────────────────── */
export const Checkbox = {
  name: 'Checkbox',
  components: { Icon },
  props: {
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  template: `
    <label class="checkbox-row" @click.prevent="$emit('update:modelValue', !modelValue)">
      <div :class="['checkbox', { checked: modelValue }]" role="checkbox" :aria-checked="modelValue">
        <Icon name="check" :size="12" />
      </div>
      <span class="checkbox-text" v-if="label">{{ label }}</span>
      <slot />
    </label>
  `,
};

/* ─── ورودی جستجو با تأخیر ────────────────────────────────── */
export const SearchInput = {
  name: 'SearchInput',
  components: { Icon },
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'جستجو…' },
    delay: { type: Number, default: 400 },
  },
  emits: ['update:modelValue', 'search'],
  setup(props, { emit }) {
    const local = ref(props.modelValue);
    let timer = null;

    // تغییر از بیرون (مثلاً پاک‌کردن فیلترها) باید در ورودی هم دیده شود
    watch(() => props.modelValue, (value) => {
      if (value !== local.value) local.value = value;
    });

    // جستجو با تأخیر انجام می‌شود تا با هر کلید یک درخواست فرستاده نشود
    watch(local, (value) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        emit('update:modelValue', value);
        emit('search', value);
      }, props.delay);
    });

    onBeforeUnmount(() => clearTimeout(timer));

    return { local };
  },
  template: `
    <div class="search-box">
      <Icon name="search" :size="16" />
      <input v-model="local" type="search" class="input" :placeholder="placeholder">
    </div>
  `,
};

/* ─── ورودی برچسب‌ها ──────────────────────────────────────── */
export const TagInput = {
  name: 'TagInput',
  components: { Icon },
  props: {
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'برچسب را بنویسید و Enter بزنید' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const draft = ref('');

    const add = () => {
      // چند برچسب جداشده با کاما هم پذیرفته می‌شود
      const names = draft.value
        .split(',')
        .map((name) => name.trim())
        .filter((name) => name && !props.modelValue.includes(name));

      if (names.length) {
        emit('update:modelValue', [...props.modelValue, ...names]);
      }

      draft.value = '';
    };

    const remove = (index) => {
      const next = [...props.modelValue];
      next.splice(index, 1);
      emit('update:modelValue', next);
    };

    // Backspace روی ورودی خالی آخرین برچسب را حذف می‌کند
    const onBackspace = () => {
      if (draft.value === '' && props.modelValue.length) {
        remove(props.modelValue.length - 1);
      }
    };

    return { draft, add, remove, onBackspace };
  },
  template: `
    <div>
      <input v-model="draft" type="text" class="input" :placeholder="placeholder"
             @keydown.enter.prevent="add" @keydown.delete="onBackspace" @blur="add">
      <div class="flex gap-xs mt" style="flex-wrap:wrap" v-if="modelValue.length">
        <span class="chip" v-for="(tag, i) in modelValue" :key="tag">
          {{ tag }}
          <button type="button" @click="remove(i)" :aria-label="'حذف ' + tag">
            <Icon name="x" :size="13" />
          </button>
        </span>
      </div>
    </div>
  `,
};
