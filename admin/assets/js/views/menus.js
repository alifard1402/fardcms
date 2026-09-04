/**
 * ساخت فهرست‌های ناوبری
 *
 * آیتم‌ها به‌صورت درختی نگه‌داری می‌شوند و با کشیدن و رها کردن
 * قابل جابه‌جایی و تودرتو کردن هستند.
 */

import { Icon } from '../icons.js';
import { Modal, EmptyState, LoadingBlock, ConfirmDialog } from '../components/ui.js';
import { api } from '../api.js';
import { notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

export const MenusView = {
  name: 'MenusView',
  components: { Icon, Modal, EmptyState, LoadingBlock, ConfirmDialog },
  setup() {
    const menus = ref([]);
    const locations = ref({});
    const sources = ref({ pages: [], posts: [], categories: [], tags: [] });
    const loading = ref(true);
    const saving = ref(false);
    const activeIndex = ref(0);
    const showMenuForm = ref(false);
    const menuForm = ref({ id: null, name: '', location: '' });
    const showItemForm = ref(false);
    const itemForm = ref({});
    const confirmDelete = ref(null);
    const busy = ref(false);
    const dragItem = ref(null);
    const dragOverId = ref(null);

    const active = computed(() => menus.value[activeIndex.value] || null);

    const load = async () => {
      loading.value = true;

      try {
        const data = await api.menus.list();

        menus.value = data.menus || [];
        locations.value = data.locations || {};
        sources.value = {
          pages: data.pages || [],
          posts: data.posts || [],
          categories: data.categories || [],
          tags: data.tags || [],
        };

        if (activeIndex.value >= menus.value.length) {
          activeIndex.value = 0;
        }
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    /* ─── مدیریت فهرست ─────────────────────────────────────── */
    const openMenuForm = (menu = null) => {
      menuForm.value = menu
        ? { id: menu.id, name: menu.name, location: menu.location }
        : { id: null, name: '', location: '' };

      showMenuForm.value = true;
    };

    const saveMenuMeta = async () => {
      if (!menuForm.value.name.trim()) {
        notify('نام فهرست را وارد کنید', 'error');
        return;
      }

      busy.value = true;

      try {
        const payload = { ...menuForm.value };

        // آیتم‌های فهرست موجود باید حفظ شوند
        if (payload.id && active.value?.id === payload.id) {
          payload.items = active.value.items;
        }

        const data = await api.menus.save(payload);

        menus.value = data.menus || menus.value;
        notify('فهرست ذخیره شد');

        // فهرست تازه‌ساخته‌شده انتخاب می‌شود
        const index = menus.value.findIndex((m) => m.id === data.menu_id);
        if (index !== -1) activeIndex.value = index;

        showMenuForm.value = false;
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const removeMenu = async () => {
      busy.value = true;

      try {
        const data = await api.menus.remove(confirmDelete.value.id);

        menus.value = data.menus || [];
        activeIndex.value = 0;
        notify('فهرست حذف شد');
        confirmDelete.value = null;
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    /* ─── مدیریت آیتم‌ها ───────────────────────────────────── */
    const openItemForm = () => {
      itemForm.value = { title: '', type: 'custom', url: '', object_id: null, target: '', icon: '' };
      showItemForm.value = true;
    };

    /** فهرست گزینه‌های قابل انتخاب بر اساس نوع آیتم */
    const objectChoices = computed(() => ({
      page: sources.value.pages,
      post: sources.value.posts,
      category: sources.value.categories,
      tag: sources.value.tags,
    }[itemForm.value.type] || []));

    const addItem = () => {
      const form = itemForm.value;

      // برای انواع پیوندی، عنوان از خود محتوا گرفته می‌شود
      if (form.type !== 'custom') {
        const choice = objectChoices.value.find((c) => c.id === form.object_id);

        if (!choice) {
          notify('یک مورد را انتخاب کنید', 'error');
          return;
        }

        if (!form.title.trim()) {
          form.title = choice.title || choice.name;
        }
      }

      if (!form.title.trim()) {
        notify('عنوان آیتم را وارد کنید', 'error');
        return;
      }

      if (form.type === 'custom' && !form.url.trim()) {
        notify('آدرس پیوند را وارد کنید', 'error');
        return;
      }

      active.value.items.push({ ...form, children: [] });
      showItemForm.value = false;
    };

    /**
     * حذف یک آیتم از هر جای درخت
     *
     * فرزندان آیتم حذف‌شده به جای آن قرار می‌گیرند تا از بین نروند.
     */
    const removeItem = (nodes, target) => {
      const index = nodes.indexOf(target);

      if (index !== -1) {
        nodes.splice(index, 1, ...(target.children || []));
        return true;
      }

      return nodes.some((node) => node.children && removeItem(node.children, target));
    };

    const deleteItem = (item) => {
      removeItem(active.value.items, item);
    };

    /** جابه‌جایی یک آیتم بین خواهر و برادرهایش */
    const moveItem = (nodes, item, direction) => {
      const index = nodes.indexOf(item);

      if (index !== -1) {
        const target = index + direction;

        if (target >= 0 && target < nodes.length) {
          nodes.splice(target, 0, nodes.splice(index, 1)[0]);
        }

        return true;
      }

      return nodes.some((node) => node.children && moveItem(node.children, item, direction));
    };

    /** تودرتو کردن: آیتم زیرمجموعه آیتم بالایی خود می‌شود */
    const indentItem = (nodes, item) => {
      const index = nodes.indexOf(item);

      if (index > 0) {
        const previous = nodes[index - 1];
        previous.children = previous.children || [];
        previous.children.push(nodes.splice(index, 1)[0]);

        return true;
      }

      if (index === 0) return false;

      return nodes.some((node) => node.children && indentItem(node.children, item));
    };

    /** خارج کردن از تودرتویی: آیتم هم‌سطح والدش می‌شود */
    const outdentItem = (nodes, item, parentList = null, parent = null) => {
      const index = nodes.indexOf(item);

      if (index !== -1) {
        if (!parentList || !parent) return false;

        const parentIndex = parentList.indexOf(parent);
        parentList.splice(parentIndex + 1, 0, nodes.splice(index, 1)[0]);

        return true;
      }

      return nodes.some(
        (node) => node.children && outdentItem(node.children, item, nodes, node)
      );
    };

    const saveItems = async () => {
      if (!active.value) return;

      saving.value = true;

      try {
        const data = await api.menus.save({
          id: active.value.id,
          name: active.value.name,
          location: active.value.location,
          items: active.value.items,
        });

        menus.value = data.menus || menus.value;
        notify('آیتم‌های فهرست ذخیره شد');
      } catch (error) {
        notifyError(error);
      } finally {
        saving.value = false;
      }
    };

    const typeLabel = (type) => ({
      custom: 'پیوند دلخواه',
      page: 'برگه',
      post: 'نوشته',
      category: 'دسته',
      tag: 'برچسب',
    }[type] || type);

    return {
      menus, locations, sources, loading, saving, activeIndex, active,
      showMenuForm, menuForm, showItemForm, itemForm, objectChoices,
      confirmDelete, busy, dragItem, dragOverId,
      load, openMenuForm, saveMenuMeta, removeMenu,
      openItemForm, addItem, deleteItem, saveItems, typeLabel,
      moveUp: (item) => moveItem(active.value.items, item, -1),
      moveDown: (item) => moveItem(active.value.items, item, 1),
      indent: (item) => indentItem(active.value.items, item),
      outdent: (item) => outdentItem(active.value.items, item),
    };
  },
  template: `
    <div>
      <LoadingBlock v-if="loading" />

      <template v-else>
        <div class="toolbar">
          <div class="status-filters" v-if="menus.length">
            <button v-for="(menu, index) in menus" :key="menu.id"
                    :class="['status-filter', { active: activeIndex === index }]"
                    @click="activeIndex = index">
              {{ menu.name }}
            </button>
          </div>

          <div class="toolbar-spacer"></div>

          <button class="btn btn-secondary" @click="openMenuForm()">
            <Icon name="plus" :size="16" /> فهرست جدید
          </button>
        </div>

        <EmptyState v-if="!menus.length" icon="menu" title="هنوز فهرستی نساخته‌اید"
                    text="فهرست‌های ناوبری، منوی سربرگ و پاورقی سایت شما را می‌سازند.">
          <button class="btn btn-primary" @click="openMenuForm()">
            <Icon name="plus" :size="16" /> ساخت فهرست
          </button>
        </EmptyState>

        <div class="split" v-else-if="active">
          <div class="card">
            <div class="card-header">
              <span class="card-title">{{ active.name }}</span>
              <span class="badge badge-accent" v-if="active.location">
                {{ locations[active.location] }}
              </span>
              <div class="grow"></div>
              <button class="btn btn-sm btn-ghost" @click="openMenuForm(active)">
                <Icon name="settings" :size="14" /> تنظیمات
              </button>
              <button class="btn btn-sm btn-ghost" @click="confirmDelete = active">
                <Icon name="trash" :size="14" />
              </button>
            </div>

            <div class="card-body">
              <EmptyState v-if="!active.items.length" icon="menu" title="این فهرست خالی است"
                          text="از کادر کنار، برگه‌ها و پیوندهای دلخواه را اضافه کنید." />

              <div class="menu-tree" v-else>
                <MenuNode v-for="(item, index) in active.items" :key="index"
                          :item="item" :depth="0"
                          @delete="deleteItem" @up="moveUp" @down="moveDown"
                          @indent="indent" @outdent="outdent" />
              </div>
            </div>

            <div class="pagination" style="justify-content:flex-end">
              <button class="btn btn-primary" @click="saveItems" :disabled="saving">
                <span v-if="saving" class="spinner"></span>
                <Icon v-else name="save" :size="16" />
                ذخیره فهرست
              </button>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><span class="card-title">افزودن آیتم</span></div>
            <div class="card-body">
              <button class="btn btn-primary btn-block" @click="openItemForm">
                <Icon name="plus" :size="16" /> آیتم جدید
              </button>

              <div class="divider"></div>

              <p class="small muted mb-0">
                برای تودرتو کردن آیتم‌ها از دکمه‌های
                <Icon name="chevronLeft" :size="12" /> و
                <Icon name="chevronRight" :size="12" />
                روی هر آیتم استفاده کنید. تغییرات پس از فشردن «ذخیره فهرست» اعمال می‌شود.
              </p>
            </div>
          </div>
        </div>
      </template>

      <!-- تنظیمات فهرست -->
      <Modal v-if="showMenuForm" :title="menuForm.id ? 'تنظیمات فهرست' : 'فهرست جدید'"
             size="sm" :busy="busy" @close="showMenuForm = false">
        <div class="field">
          <label class="field-label">نام فهرست <span class="req">*</span></label>
          <input v-model="menuForm.name" type="text" class="input" placeholder="فهرست اصلی"
                 @keydown.enter="saveMenuMeta">
        </div>
        <div class="field mb-0">
          <label class="field-label">جایگاه در قالب</label>
          <select v-model="menuForm.location" class="select">
            <option value="">— بدون جایگاه —</option>
            <option v-for="(label, key) in locations" :key="key" :value="key">{{ label }}</option>
          </select>
          <div class="field-hint">هر جایگاه فقط به یک فهرست تعلق می‌گیرد.</div>
        </div>
        <template #footer>
          <button class="btn btn-ghost" @click="showMenuForm = false" :disabled="busy">انصراف</button>
          <button class="btn btn-primary" @click="saveMenuMeta" :disabled="busy">
            <span v-if="busy" class="spinner"></span> ذخیره
          </button>
        </template>
      </Modal>

      <!-- افزودن آیتم -->
      <Modal v-if="showItemForm" title="افزودن آیتم به فهرست" size="sm" @close="showItemForm = false">
        <div class="field">
          <label class="field-label">نوع آیتم</label>
          <select v-model="itemForm.type" class="select" @change="itemForm.object_id = null">
            <option value="custom">پیوند دلخواه</option>
            <option value="page">برگه</option>
            <option value="post">نوشته</option>
            <option value="category">دسته‌بندی</option>
            <option value="tag">برچسب</option>
          </select>
        </div>

        <div class="field" v-if="itemForm.type !== 'custom'">
          <label class="field-label">انتخاب مورد <span class="req">*</span></label>
          <select v-model.number="itemForm.object_id" class="select">
            <option :value="null">— انتخاب کنید —</option>
            <option v-for="choice in objectChoices" :key="choice.id" :value="choice.id">
              {{ choice.title || choice.name }}
            </option>
          </select>
        </div>

        <div class="field" v-if="itemForm.type === 'custom'">
          <label class="field-label">آدرس <span class="req">*</span></label>
          <input v-model="itemForm.url" type="text" class="input input-ltr"
                 placeholder="https://example.com یا /about">
        </div>

        <div class="field">
          <label class="field-label">عنوان نمایشی</label>
          <input v-model="itemForm.title" type="text" class="input"
                 placeholder="اگر خالی بماند از عنوان مورد استفاده می‌شود">
        </div>

        <label class="checkbox-row mb-0">
          <div :class="['switch', { on: itemForm.target === '_blank' }]"
               @click="itemForm.target = itemForm.target === '_blank' ? '' : '_blank'"></div>
          <span class="checkbox-text">باز شدن در پنجره جدید</span>
        </label>

        <template #footer>
          <button class="btn btn-ghost" @click="showItemForm = false">انصراف</button>
          <button class="btn btn-primary" @click="addItem">افزودن</button>
        </template>
      </Modal>

      <ConfirmDialog v-if="confirmDelete"
                     :message="'فهرست «' + confirmDelete.name + '» و تمام آیتم‌هایش حذف می‌شود.'"
                     confirm-label="حذف فهرست"
                     danger
                     :busy="busy"
                     @confirm="removeMenu"
                     @cancel="confirmDelete = null" />
    </div>
  `,
};

/**
 * یک گره از درخت فهرست (بازگشتی)
 */
const MenuNode = {
  name: 'MenuNode',
  components: { Icon },
  props: {
    item: { type: Object, required: true },
    depth: { type: Number, default: 0 },
  },
  emits: ['delete', 'up', 'down', 'indent', 'outdent'],
  setup() {
    const typeLabel = (type) => ({
      custom: 'پیوند دلخواه',
      page: 'برگه',
      post: 'نوشته',
      category: 'دسته',
      tag: 'برچسب',
    }[type] || type);

    return { typeLabel };
  },
  template: `
    <div class="menu-node">
      <div class="menu-node-head">
        <span class="drag-handle"><Icon name="grip" :size="15" /></span>
        <div class="grow" style="min-width:0">
          <div class="menu-node-title truncate">{{ item.title }}</div>
          <div class="menu-node-type">
            {{ typeLabel(item.type) }}
            <span v-if="item.type === 'custom' && item.url" class="mono"> · {{ item.url }}</span>
            <span v-if="item.target === '_blank'"> · پنجره جدید</span>
          </div>
        </div>
        <div class="row-actions">
          <button class="icon-btn" @click="$emit('up', item)" title="بالا" style="width:30px;height:30px">
            <Icon name="chevronDown" :size="14" style="transform:rotate(180deg)" />
          </button>
          <button class="icon-btn" @click="$emit('down', item)" title="پایین" style="width:30px;height:30px">
            <Icon name="chevronDown" :size="14" />
          </button>
          <button class="icon-btn" @click="$emit('indent', item)" title="زیرمجموعه کردن"
                  style="width:30px;height:30px" v-if="depth < 2">
            <Icon name="chevronLeft" :size="14" />
          </button>
          <button class="icon-btn" @click="$emit('outdent', item)" title="خارج کردن"
                  style="width:30px;height:30px" v-if="depth > 0">
            <Icon name="chevronRight" :size="14" />
          </button>
          <button class="icon-btn" @click="$emit('delete', item)" title="حذف" style="width:30px;height:30px">
            <Icon name="trash" :size="14" />
          </button>
        </div>
      </div>

      <div class="menu-children" v-if="item.children?.length">
        <MenuNode v-for="(child, index) in item.children" :key="index"
                  :item="child" :depth="depth + 1"
                  @delete="$emit('delete', $event)"
                  @up="$emit('up', $event)"
                  @down="$emit('down', $event)"
                  @indent="$emit('indent', $event)"
                  @outdent="$emit('outdent', $event)" />
      </div>
    </div>
  `,
};

// ثبت بازگشتی: گره باید بتواند خودش را رندر کند
MenuNode.components.MenuNode = MenuNode;
MenusView.components.MenuNode = MenuNode;

export { MenuNode };
