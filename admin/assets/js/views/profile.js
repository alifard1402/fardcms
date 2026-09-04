/**
 * پروفایل کاربر — ویرایش اطلاعات، تغییر رمز و نشست‌های فعال
 */

import { Icon } from '../icons.js';
import { LoadingBlock } from '../components/ui.js';
import { MediaPicker } from '../components/media-picker.js';
import { api } from '../api.js';
import { store, notify, notifyError } from '../store.js';

const { ref, onMounted } = Vue;

export const ProfileView = {
  name: 'ProfileView',
  components: { Icon, LoadingBlock, MediaPicker },
  setup() {
    const user = ref(null);
    const sessions = ref([]);
    const loading = ref(true);
    const saving = ref(false);
    const changingPassword = ref(false);
    const showPicker = ref(false);

    const form = ref({ name: '', username: '', email: '', bio: '', avatar: '' });
    const passwordForm = ref({ current: '', next: '', confirm: '' });
    const errors = ref({});
    const passwordErrors = ref({});
    const visible = ref({ current: false, next: false });

    const load = async () => {
      loading.value = true;

      try {
        const data = await api.users.profile();

        user.value = data.user;
        sessions.value = data.sessions || [];

        form.value = {
          name: data.user.name,
          username: data.user.username,
          email: data.user.email,
          bio: data.user.bio || '',
          avatar: data.user.avatar || '',
        };
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    /* ─── ذخیره اطلاعات ────────────────────────────────────── */
    const save = async () => {
      errors.value = {};

      if (!form.value.name.trim()) errors.value.name = 'نام الزامی است';
      if (!/^\S+@\S+\.\S+$/.test(form.value.email)) errors.value.email = 'ایمیل نامعتبر است';

      if (Object.keys(errors.value).length) return;

      saving.value = true;

      try {
        const data = await api.users.saveProfile(form.value);

        // اطلاعات نمایشی در نوار بالا هم به‌روزرسانی می‌شود
        Object.assign(store.user, data.user);

        notify('پروفایل به‌روزرسانی شد');
      } catch (error) {
        notifyError(error);
      } finally {
        saving.value = false;
      }
    };

    /* ─── تغییر رمز عبور ───────────────────────────────────── */
    const changePassword = async () => {
      passwordErrors.value = {};
      const { current, next, confirm } = passwordForm.value;

      if (!current) passwordErrors.value.current = 'رمز عبور فعلی را وارد کنید';

      if (next.length < 8) {
        passwordErrors.value.next = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
      } else if (!/[A-Z]/.test(next) || !/[a-z]/.test(next) || !/[0-9]/.test(next)) {
        passwordErrors.value.next = 'رمز عبور باید شامل حروف بزرگ، کوچک و عدد باشد';
      }

      if (next !== confirm) passwordErrors.value.confirm = 'تکرار رمز عبور مطابقت ندارد';

      if (Object.keys(passwordErrors.value).length) return;

      changingPassword.value = true;

      try {
        await api.users.changePassword(current, next);

        notify('رمز عبور تغییر کرد. سایر دستگاه‌ها از حساب خارج شدند.');
        passwordForm.value = { current: '', next: '', confirm: '' };

        // فهرست نشست‌ها پس از خروج سایر دستگاه‌ها تغییر کرده است
        await load();
      } catch (error) {
        notifyError(error);
      } finally {
        changingPassword.value = false;
      }
    };

    const onAvatarSelect = (items) => {
      showPicker.value = false;

      if (items.length) {
        form.value.avatar = items[0].url;
      }
    };

    const initials = (name) => (name || '')
      .split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('');

    /** خلاصه‌ی خوانا از رشته User-Agent */
    const deviceLabel = (userAgent) => {
      const ua = userAgent || '';

      const browser = /Firefox\//.test(ua) ? 'فایرفاکس'
        : /Edg\//.test(ua) ? 'اج'
        : /Chrome\//.test(ua) ? 'کروم'
        : /Safari\//.test(ua) ? 'سافاری'
        : 'مرورگر ناشناس';

      const os = /Windows/.test(ua) ? 'ویندوز'
        : /Android/.test(ua) ? 'اندروید'
        : /iPhone|iPad/.test(ua) ? 'iOS'
        : /Mac OS/.test(ua) ? 'مک'
        : /Linux/.test(ua) ? 'لینوکس'
        : '';

      return os ? `${browser} — ${os}` : browser;
    };

    return {
      user, sessions, loading, saving, changingPassword, showPicker,
      form, passwordForm, errors, passwordErrors, visible,
      save, changePassword, onAvatarSelect, initials, deviceLabel,
    };
  },
  template: `
    <div>
      <LoadingBlock v-if="loading" />

      <div class="split" v-else-if="user">
        <div class="stack">
          <!-- اطلاعات حساب -->
          <div class="card">
            <div class="card-header"><span class="card-title">اطلاعات حساب</span></div>
            <div class="card-body">
              <div class="form-grid">
                <div class="field">
                  <label class="field-label">نام نمایشی <span class="req">*</span></label>
                  <input v-model="form.name" type="text" :class="['input', { 'has-error': errors.name }]">
                  <div class="field-error" v-if="errors.name">{{ errors.name }}</div>
                </div>

                <div class="field">
                  <label class="field-label">نام کاربری</label>
                  <input v-model="form.username" type="text" class="input input-ltr">
                  <div class="field-hint">برای ورود می‌توانید از نام کاربری یا ایمیل استفاده کنید.</div>
                </div>
              </div>

              <div class="field">
                <label class="field-label">ایمیل <span class="req">*</span></label>
                <input v-model="form.email" type="email"
                       :class="['input', 'input-ltr', { 'has-error': errors.email }]">
                <div class="field-error" v-if="errors.email">{{ errors.email }}</div>
              </div>

              <div class="field mb-0">
                <label class="field-label">درباره من</label>
                <textarea v-model="form.bio" class="textarea" rows="3" maxlength="500"
                          placeholder="توضیح کوتاهی که در صفحه نوشته‌های شما نمایش داده می‌شود."></textarea>
              </div>

              <div class="form-actions">
                <button class="btn btn-primary" @click="save" :disabled="saving">
                  <span v-if="saving" class="spinner"></span>
                  <Icon v-else name="save" :size="16" />
                  ذخیره تغییرات
                </button>
              </div>
            </div>
          </div>

          <!-- تغییر رمز عبور -->
          <div class="card">
            <div class="card-header">
              <Icon name="lock" :size="17" class="faint" />
              <span class="card-title">تغییر رمز عبور</span>
            </div>
            <div class="card-body">
              <div class="field">
                <label class="field-label">رمز عبور فعلی <span class="req">*</span></label>
                <div style="position:relative">
                  <input v-model="passwordForm.current" :type="visible.current ? 'text' : 'password'"
                         :class="['input', { 'has-error': passwordErrors.current }]"
                         style="padding-inline-start:44px" autocomplete="current-password">
                  <button type="button" class="icon-btn"
                          style="position:absolute;inset-inline-start:4px;top:50%;transform:translateY(-50%);width:34px;height:34px"
                          @click="visible.current = !visible.current">
                    <Icon :name="visible.current ? 'eyeOff' : 'eye'" :size="16" />
                  </button>
                </div>
                <div class="field-error" v-if="passwordErrors.current">{{ passwordErrors.current }}</div>
              </div>

              <div class="form-grid">
                <div class="field">
                  <label class="field-label">رمز عبور جدید <span class="req">*</span></label>
                  <div style="position:relative">
                    <input v-model="passwordForm.next" :type="visible.next ? 'text' : 'password'"
                           :class="['input', { 'has-error': passwordErrors.next }]"
                           style="padding-inline-start:44px" autocomplete="new-password">
                    <button type="button" class="icon-btn"
                            style="position:absolute;inset-inline-start:4px;top:50%;transform:translateY(-50%);width:34px;height:34px"
                            @click="visible.next = !visible.next">
                      <Icon :name="visible.next ? 'eyeOff' : 'eye'" :size="16" />
                    </button>
                  </div>
                  <div class="field-error" v-if="passwordErrors.next">{{ passwordErrors.next }}</div>
                </div>

                <div class="field">
                  <label class="field-label">تکرار رمز جدید <span class="req">*</span></label>
                  <input v-model="passwordForm.confirm" type="password"
                         :class="['input', { 'has-error': passwordErrors.confirm }]"
                         autocomplete="new-password">
                  <div class="field-error" v-if="passwordErrors.confirm">{{ passwordErrors.confirm }}</div>
                </div>
              </div>

              <div class="alert alert-info mb-0" style="margin-top:6px">
                <Icon name="info" :size="18" />
                <div>پس از تغییر رمز عبور، تمام دستگاه‌های دیگر از حساب شما خارج می‌شوند.</div>
              </div>

              <div class="form-actions">
                <button class="btn btn-primary" @click="changePassword" :disabled="changingPassword">
                  <span v-if="changingPassword" class="spinner"></span>
                  <Icon v-else name="key" :size="16" />
                  تغییر رمز عبور
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="stack">
          <!-- تصویر پروفایل -->
          <div class="card">
            <div class="card-header"><span class="card-title">تصویر پروفایل</span></div>
            <div class="card-body center">
              <img v-if="form.avatar" :src="form.avatar" class="avatar avatar-lg"
                   style="margin:0 auto 15px" :alt="form.name">
              <div v-else class="avatar avatar-lg" style="margin:0 auto 15px">
                {{ initials(form.name) }}
              </div>

              <div class="flex gap-sm" style="justify-content:center">
                <button class="btn btn-sm btn-secondary" @click="showPicker = true">
                  <Icon name="image" :size="14" /> انتخاب تصویر
                </button>
                <button class="btn btn-sm btn-ghost" v-if="form.avatar" @click="form.avatar = ''">
                  <Icon name="x" :size="14" /> حذف
                </button>
              </div>

              <div class="field-hint mt">فراموش نکنید تغییرات را ذخیره کنید.</div>
            </div>
          </div>

          <!-- خلاصه حساب -->
          <div class="card">
            <div class="card-header"><span class="card-title">وضعیت حساب</span></div>
            <div class="card-body">
              <div class="flex" style="justify-content:space-between;margin-bottom:9px">
                <span class="small muted">نقش</span>
                <span class="badge badge-accent">{{ user.role_label }}</span>
              </div>
              <div class="flex" style="justify-content:space-between;margin-bottom:9px">
                <span class="small muted">تاریخ عضویت</span>
                <span class="small">{{ user.created_jalali }}</span>
              </div>
              <div class="flex" style="justify-content:space-between">
                <span class="small muted">آخرین ورود</span>
                <span class="small">{{ user.last_login_relative }}</span>
              </div>
            </div>
          </div>

          <!-- نشست‌های فعال -->
          <div class="card">
            <div class="card-header">
              <Icon name="activity" :size="17" class="faint" />
              <span class="card-title">دستگاه‌های فعال</span>
            </div>
            <div class="card-body tight">
              <div class="simple-list">
                <div v-for="(session, index) in sessions" :key="index" class="simple-item">
                  <div class="simple-body">
                    <div class="simple-title" style="font-weight:500">
                      {{ deviceLabel(session.user_agent) }}
                      <span class="badge badge-success" v-if="session.is_current">این دستگاه</span>
                    </div>
                    <div class="simple-meta">
                      <span class="mono">{{ session.ip_address }}</span>
                      · {{ session.last_activity_relative }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <MediaPicker v-if="showPicker" images-only title="انتخاب تصویر پروفایل"
                   @select="onAvatarSelect" @close="showPicker = false" />
    </div>
  `,
};
