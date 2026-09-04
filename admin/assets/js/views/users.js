/**
 * مدیریت کاربران — ایجاد، ویرایش نقش و حذف
 */

import { Icon } from '../icons.js';
import {
  Modal, Pagination, EmptyState, LoadingBlock, SearchInput, Switch, ConfirmDialog,
} from '../components/ui.js';
import { api } from '../api.js';
import { store, notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

export const UsersView = {
  name: 'UsersView',
  components: { Icon, Modal, Pagination, EmptyState, LoadingBlock, SearchInput, Switch, ConfirmDialog },
  setup() {
    const users = ref([]);
    const meta = ref({ total: 0, page: 1, per_page: 20, total_pages: 0 });
    const counts = ref({});
    const roles = ref([]);
    const loading = ref(true);
    const search = ref('');
    const roleFilter = ref('');
    const editing = ref(null);
    const form = ref({});
    const confirmDelete = ref(null);
    const reassignTo = ref(0);
    const busy = ref(false);
    const errors = ref({});
    const showPassword = ref(false);

    const emptyForm = () => ({
      name: '',
      username: '',
      email: '',
      password: '',
      role: 'subscriber',
      bio: '',
      is_active: true,
    });

    const load = async (page = 1) => {
      loading.value = true;

      try {
        const data = await api.users.list({
          search: search.value,
          role: roleFilter.value,
          page,
          per_page: 20,
        });

        users.value = data.users || [];
        meta.value = data.meta || meta.value;
        counts.value = data.counts || {};
        roles.value = data.roles || [];
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => load());

    const setRoleFilter = (value) => {
      roleFilter.value = value;
      load(1);
    };

    /* ─── فرم ──────────────────────────────────────────────── */
    const openNew = () => {
      editing.value = { id: null };
      form.value = emptyForm();
      errors.value = {};
      showPassword.value = false;
    };

    const openEdit = (user) => {
      editing.value = user;
      form.value = {
        name: user.name,
        username: user.username,
        email: user.email,
        password: '',
        role: user.role,
        bio: user.bio || '',
        is_active: user.is_active,
      };
      errors.value = {};
      showPassword.value = false;
    };

    const validate = () => {
      errors.value = {};

      if (!form.value.name.trim()) errors.value.name = 'نام الزامی است';
      if (!/^\S+@\S+\.\S+$/.test(form.value.email)) errors.value.email = 'ایمیل نامعتبر است';

      if (form.value.username && !/^[a-zA-Z0-9._-]{3,50}$/.test(form.value.username)) {
        errors.value.username = 'نام کاربری باید ۳ تا ۵۰ کاراکتر لاتین باشد';
      }

      // رمز عبور فقط برای کاربر جدید الزامی است
      const needsPassword = !editing.value.id;

      if (needsPassword || form.value.password) {
        if (form.value.password.length < 8) {
          errors.value.password = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
        } else if (!/[A-Z]/.test(form.value.password)
                || !/[a-z]/.test(form.value.password)
                || !/[0-9]/.test(form.value.password)) {
          errors.value.password = 'رمز عبور باید شامل حروف بزرگ، کوچک و عدد باشد';
        }
      }

      return Object.keys(errors.value).length === 0;
    };

    const save = async () => {
      if (!validate()) return;

      busy.value = true;

      const payload = { id: editing.value.id, ...form.value };

      // رمز خالی در حالت ویرایش یعنی «تغییر نده»
      if (!payload.password) delete payload.password;

      try {
        await api.users.save(payload);

        notify(editing.value.id ? 'تغییرات ذخیره شد' : 'کاربر ایجاد شد');
        editing.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    const askDelete = (user) => {
      confirmDelete.value = user;
      reassignTo.value = 0;
    };

    const remove = async () => {
      busy.value = true;

      try {
        await api.users.remove(confirmDelete.value.id, reassignTo.value);

        notify('کاربر حذف شد');
        confirmDelete.value = null;
        await load(meta.value.page);
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = false;
      }
    };

    /** کاربرانی که محتوای کاربر حذف‌شده می‌تواند به آن‌ها منتقل شود */
    const reassignChoices = computed(
      () => users.value.filter((u) => u.id !== confirmDelete.value?.id)
    );

    const roleTabs = computed(() => {
      const tabs = [{ value: '', label: 'همه', count: counts.value.all }];

      roles.value.forEach((role) => {
        if (counts.value[role.value] > 0) {
          tabs.push({ value: role.value, label: role.label, count: counts.value[role.value] });
        }
      });

      return tabs;
    });

    const roleTone = (role) => ({
      administrator: 'badge-accent',
      editor: 'badge-info',
      author: 'badge-success',
      contributor: 'badge-warning',
      subscriber: 'badge-muted',
    }[role] || 'badge-muted');

    const initials = (name) => (name || '')
      .split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('');

    return {
      users, meta, counts, roles, loading, search, roleFilter, editing, form,
      confirmDelete, reassignTo, reassignChoices, busy, errors, showPassword,
      roleTabs, roleTone, initials, store,
      load, setRoleFilter, openNew, openEdit, save, askDelete, remove,
      fa: (n) => (n || 0).toLocaleString('fa-IR'),
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <SearchInput v-model="search" placeholder="جستجوی کاربر…" @search="load(1)" />

        <div class="status-filters">
          <button v-for="tab in roleTabs" :key="tab.value"
                  :class="['status-filter', { active: roleFilter === tab.value }]"
                  @click="setRoleFilter(tab.value)">
            {{ tab.label }}<span class="count" v-if="tab.count">({{ fa(tab.count) }})</span>
          </button>
        </div>

        <div class="toolbar-spacer"></div>

        <button class="btn btn-primary" @click="openNew">
          <Icon name="plus" :size="16" /> کاربر جدید
        </button>
      </div>

      <div class="card">
        <LoadingBlock v-if="loading" />

        <EmptyState v-else-if="!users.length" icon="users"
                    :title="search ? 'کاربری یافت نشد' : 'کاربری وجود ندارد'"
                    text="عبارت جستجو یا فیلتر نقش را تغییر دهید." />

        <template v-else>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>کاربر</th>
                  <th class="nowrap">نقش</th>
                  <th class="nowrap">نوشته‌ها</th>
                  <th class="nowrap">وضعیت</th>
                  <th class="nowrap">آخرین ورود</th>
                  <th class="col-actions"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in users" :key="user.id">
                  <td>
                    <div class="flex gap-sm">
                      <img v-if="user.avatar" :src="user.avatar" class="avatar" :alt="user.name">
                      <div v-else class="avatar">{{ initials(user.name) }}</div>
                      <div style="min-width:0">
                        <div class="cell-title">
                          {{ user.name }}
                          <span class="badge badge-accent" v-if="user.id === store.user?.id">شما</span>
                        </div>
                        <div class="cell-meta ltr">{{ user.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge" :class="roleTone(user.role)">{{ user.role_label }}</span></td>
                  <td class="muted small">{{ fa(user.post_count) }}</td>
                  <td>
                    <span class="badge" :class="user.is_active ? 'badge-success' : 'badge-danger'">
                      {{ user.is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                  </td>
                  <td class="muted small nowrap">{{ user.last_login_relative }}</td>
                  <td class="col-actions">
                    <div class="row-actions">
                      <button class="icon-btn" @click="openEdit(user)" title="ویرایش">
                        <Icon name="edit" :size="16" />
                      </button>
                      <button class="icon-btn" @click="askDelete(user)" title="حذف"
                              :disabled="user.id === store.user?.id">
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

      <!-- فرم کاربر -->
      <Modal v-if="editing" :title="editing.id ? 'ویرایش کاربر' : 'افزودن کاربر'"
             :busy="busy" @close="editing = null">
        <div class="form-grid">
          <div class="field">
            <label class="field-label">نام نمایشی <span class="req">*</span></label>
            <input v-model="form.name" type="text" :class="['input', { 'has-error': errors.name }]">
            <div class="field-error" v-if="errors.name">{{ errors.name }}</div>
          </div>

          <div class="field">
            <label class="field-label">نام کاربری</label>
            <input v-model="form.username" type="text"
                   :class="['input', 'input-ltr', { 'has-error': errors.username }]"
                   placeholder="username">
            <div class="field-error" v-if="errors.username">{{ errors.username }}</div>
            <div class="field-hint" v-else-if="!editing.id">اگر خالی بماند، از ایمیل ساخته می‌شود.</div>
          </div>
        </div>

        <div class="field">
          <label class="field-label">ایمیل <span class="req">*</span></label>
          <input v-model="form.email" type="email"
                 :class="['input', 'input-ltr', { 'has-error': errors.email }]">
          <div class="field-error" v-if="errors.email">{{ errors.email }}</div>
        </div>

        <div class="field">
          <label class="field-label">
            رمز عبور <span class="req" v-if="!editing.id">*</span>
          </label>
          <div style="position:relative">
            <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                   :class="['input', { 'has-error': errors.password }]"
                   style="padding-inline-start:44px"
                   :placeholder="editing.id ? 'برای تغییر ندادن، خالی بگذارید' : 'حداقل ۸ کاراکتر'">
            <button type="button" class="icon-btn"
                    style="position:absolute;inset-inline-start:4px;top:50%;transform:translateY(-50%);width:34px;height:34px"
                    @click="showPassword = !showPassword">
              <Icon :name="showPassword ? 'eyeOff' : 'eye'" :size="16" />
            </button>
          </div>
          <div class="field-error" v-if="errors.password">{{ errors.password }}</div>
          <div class="field-hint" v-else>باید شامل حروف بزرگ، کوچک و عدد باشد.</div>
        </div>

        <div class="field">
          <label class="field-label">نقش</label>
          <select v-model="form.role" class="select" :disabled="editing.id === store.user?.id">
            <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
          </select>
          <div class="field-hint" v-if="editing.id === store.user?.id">
            نقش حساب خودتان قابل تغییر نیست.
          </div>
        </div>

        <div class="field">
          <label class="field-label">درباره کاربر</label>
          <textarea v-model="form.bio" class="textarea" rows="2" maxlength="500"></textarea>
        </div>

        <Switch v-model="form.is_active" label="حساب فعال است"
                hint="کاربر غیرفعال نمی‌تواند وارد شود." />

        <template #footer>
          <button class="btn btn-ghost" @click="editing = null" :disabled="busy">انصراف</button>
          <button class="btn btn-primary" @click="save" :disabled="busy">
            <span v-if="busy" class="spinner"></span>
            {{ editing.id ? 'ذخیره تغییرات' : 'افزودن کاربر' }}
          </button>
        </template>
      </Modal>

      <!-- حذف کاربر با انتقال محتوا -->
      <Modal v-if="confirmDelete" title="حذف کاربر" size="sm" :busy="busy"
             @close="confirmDelete = null">
        <div class="alert alert-error">
          <Icon name="alert" :size="18" />
          <div>حساب «{{ confirmDelete.name }}» برای همیشه حذف می‌شود.</div>
        </div>

        <div class="field mb-0" v-if="confirmDelete.post_count > 0">
          <label class="field-label">
            انتقال {{ fa(confirmDelete.post_count) }} محتوای این کاربر به
          </label>
          <select v-model.number="reassignTo" class="select">
            <option :value="0">— بدون نویسنده —</option>
            <option v-for="choice in reassignChoices" :key="choice.id" :value="choice.id">
              {{ choice.name }}
            </option>
          </select>
          <div class="field-hint">
            اگر «بدون نویسنده» را انتخاب کنید، محتوا حفظ می‌شود اما نویسنده‌ای نخواهد داشت.
          </div>
        </div>

        <template #footer>
          <button class="btn btn-ghost" @click="confirmDelete = null" :disabled="busy">انصراف</button>
          <button class="btn btn-danger" @click="remove" :disabled="busy">
            <span v-if="busy" class="spinner"></span> حذف کاربر
          </button>
        </template>
      </Modal>
    </div>
  `,
};
