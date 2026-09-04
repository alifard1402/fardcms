/**
 * تنظیمات سایت
 */

import { Icon } from '../icons.js';
import { LoadingBlock, Switch } from '../components/ui.js';
import { MediaPicker } from '../components/media-picker.js';
import { api } from '../api.js';
import { store, notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

export const SettingsView = {
  name: 'SettingsView',
  components: { Icon, LoadingBlock, Switch, MediaPicker },
  setup() {
    const settings = ref({});
    const roles = ref([]);
    const pages = ref([]);
    const loading = ref(true);
    const saving = ref(false);
    const tab = ref('general');
    const picker = ref(null);      // نام فیلدی که در انتظار انتخاب تصویر است

    const tabs = [
      { value: 'general', label: 'عمومی', icon: 'settings' },
      { value: 'reading', label: 'خواندن', icon: 'posts' },
      { value: 'discussion', label: 'گفت‌وگو', icon: 'comment' },
      { value: 'social', label: 'شبکه‌های اجتماعی', icon: 'external' },
      { value: 'advanced', label: 'پیشرفته', icon: 'key' },
    ];

    const socialFields = [
      { key: 'telegram', label: 'تلگرام', placeholder: 'https://t.me/username' },
      { key: 'instagram', label: 'اینستاگرام', placeholder: 'https://instagram.com/username' },
      { key: 'x', label: 'ایکس (توییتر)', placeholder: 'https://x.com/username' },
      { key: 'github', label: 'گیت‌هاب', placeholder: 'https://github.com/username' },
    ];

    const load = async () => {
      loading.value = true;

      try {
        const data = await api.settings.get();

        settings.value = data.settings || {};
        roles.value = (data.roles || []).filter((r) => r.value !== 'administrator');
        pages.value = data.pages || [];

        // اطمینان از وجود ساختار لینک‌های اجتماعی
        if (!settings.value.social_links) {
          settings.value.social_links = {};
        }
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    const save = async () => {
      saving.value = true;

      try {
        const data = await api.settings.save(settings.value);

        settings.value = data.settings || settings.value;

        // تغییر عنوان سایت بلافاصله در نوار کناری دیده می‌شود
        store.settings.site_title = settings.value.site_title;
        document.title = `پیشخوان — ${settings.value.site_title}`;

        notify('تنظیمات ذخیره شد');
      } catch (error) {
        notifyError(error);
      } finally {
        saving.value = false;
      }
    };

    const onMediaSelect = (items) => {
      if (items.length && picker.value) {
        settings.value[picker.value] = items[0].url;
      }

      picker.value = null;
    };

    return {
      settings, roles, pages, loading, saving, tab, tabs, socialFields, picker,
      save, onMediaSelect,
    };
  },
  template: `
    <div>
      <LoadingBlock v-if="loading" />

      <template v-else>
        <div class="toolbar">
          <div class="status-filters">
            <button v-for="item in tabs" :key="item.value"
                    :class="['status-filter', { active: tab === item.value }]"
                    @click="tab = item.value">{{ item.label }}</button>
          </div>
          <div class="toolbar-spacer"></div>
          <button class="btn btn-primary" @click="save" :disabled="saving">
            <span v-if="saving" class="spinner"></span>
            <Icon v-else name="save" :size="16" />
            ذخیره تنظیمات
          </button>
        </div>

        <!-- عمومی -->
        <div class="card" v-if="tab === 'general'">
          <div class="card-header"><span class="card-title">هویت سایت</span></div>
          <div class="card-body">
            <div class="field">
              <label class="field-label">عنوان سایت</label>
              <input v-model="settings.site_title" type="text" class="input" maxlength="200">
            </div>

            <div class="field">
              <label class="field-label">شعار سایت</label>
              <input v-model="settings.site_tagline" type="text" class="input" maxlength="200">
              <div class="field-hint">در چند کلمه توضیح دهید سایت شما درباره چیست.</div>
            </div>

            <div class="field">
              <label class="field-label">توضیح سایت (برای موتورهای جستجو)</label>
              <textarea v-model="settings.site_description" class="textarea" rows="2" maxlength="300"></textarea>
            </div>

            <div class="divider"></div>

            <div class="form-grid">
              <div class="field">
                <label class="field-label">لوگوی سایت</label>
                <div class="flex gap-sm">
                  <input v-model="settings.site_logo" type="text" class="input input-ltr grow"
                         placeholder="آدرس تصویر لوگو">
                  <button class="btn btn-secondary" @click="picker = 'site_logo'">
                    <Icon name="image" :size="16" />
                  </button>
                </div>
                <img v-if="settings.site_logo" :src="settings.site_logo" alt="لوگو"
                     style="max-height:52px;margin-top:11px;border-radius:8px">
              </div>

              <div class="field">
                <label class="field-label">نمادک سایت (favicon)</label>
                <div class="flex gap-sm">
                  <input v-model="settings.site_favicon" type="text" class="input input-ltr grow"
                         placeholder="آدرس تصویر نمادک">
                  <button class="btn btn-secondary" @click="picker = 'site_favicon'">
                    <Icon name="image" :size="16" />
                  </button>
                </div>
                <img v-if="settings.site_favicon" :src="settings.site_favicon" alt="نمادک"
                     style="max-height:34px;margin-top:11px;border-radius:6px">
              </div>
            </div>

            <div class="field mb-0">
              <label class="field-label">متن پاورقی</label>
              <textarea v-model="settings.footer_text" class="textarea" rows="2" maxlength="1000"
                        placeholder="© ۱۴۰۵ — تمام حقوق محفوظ است"></textarea>
              <div class="field-hint">HTML ساده مانند پیوند و متن درشت پذیرفته می‌شود.</div>
            </div>
          </div>
        </div>

        <!-- خواندن -->
        <div class="card" v-if="tab === 'reading'">
          <div class="card-header"><span class="card-title">نمایش محتوا</span></div>
          <div class="card-body">
            <div class="field">
              <label class="field-label">صفحه اصلی سایت</label>
              <select v-model="settings.front_page" class="select">
                <option value="blog">آخرین نوشته‌ها (وبلاگ)</option>
                <option v-for="page in pages" :key="page.id" :value="String(page.id)">
                  برگه: {{ page.title }}
                </option>
              </select>
              <div class="field-hint">می‌توانید یک برگه ثابت را به‌عنوان صفحه اصلی انتخاب کنید.</div>
            </div>

            <div class="field">
              <label class="field-label">تعداد نوشته در هر صفحه</label>
              <input v-model.number="settings.posts_per_page" type="number" class="input" min="1" max="100">
              <div class="field-hint">عددی بین ۱ تا ۱۰۰.</div>
            </div>

            <div class="field mb-0">
              <label class="field-label">قالب تاریخ</label>
              <select v-model="settings.date_format" class="select">
                <option value="j F Y">۱۳ شهریور ۱۴۰۵</option>
                <option value="Y/m/d">۱۴۰۵/۰۶/۱۳</option>
                <option value="j F Y — H:i">۱۳ شهریور ۱۴۰۵ — ۱۰:۳۰</option>
              </select>
            </div>
          </div>
        </div>

        <!-- گفت‌وگو -->
        <div class="card" v-if="tab === 'discussion'">
          <div class="card-header"><span class="card-title">دیدگاه‌ها و عضویت</span></div>
          <div class="card-body flex-col gap-lg">
            <Switch v-model="settings.allow_comments" label="اجازه ارسال دیدگاه در سایت"
                    hint="با خاموش کردن این گزینه، فرم دیدگاه در تمام نوشته‌ها پنهان می‌شود." />

            <Switch v-model="settings.moderate_comments" label="دیدگاه‌ها پیش از نمایش تأیید شوند"
                    hint="توصیه می‌شود روشن بماند تا از انتشار هرزنامه جلوگیری شود." />

            <div class="divider" style="margin:0"></div>

            <Switch v-model="settings.allow_registration" label="اجازه ثبت‌نام کاربران جدید"
                    hint="اگر خاموش باشد، فقط مدیر می‌تواند کاربر بسازد." />

            <div class="field mb-0" v-if="settings.allow_registration">
              <label class="field-label">نقش پیش‌فرض کاربران جدید</label>
              <select v-model="settings.default_role" class="select">
                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
              </select>
              <div class="field-hint">
                برای امنیت، نقش «مدیر کل» در این فهرست قابل انتخاب نیست.
              </div>
            </div>
          </div>
        </div>

        <!-- شبکه‌های اجتماعی -->
        <div class="card" v-if="tab === 'social'">
          <div class="card-header">
            <span class="card-title">پیوند شبکه‌های اجتماعی</span>
            <span class="small faint">در پاورقی سایت نمایش داده می‌شود</span>
          </div>
          <div class="card-body">
            <div class="field" v-for="field in socialFields" :key="field.key"
                 :class="{ 'mb-0': field.key === 'github' }">
              <label class="field-label">{{ field.label }}</label>
              <input v-model="settings.social_links[field.key]" type="url"
                     class="input input-ltr" :placeholder="field.placeholder">
            </div>
            <div class="field-hint mt">آدرس باید کامل و با https:// شروع شود؛ در غیر این صورت ذخیره نمی‌شود.</div>
          </div>
        </div>

        <!-- پیشرفته -->
        <div class="card" v-if="tab === 'advanced'">
          <div class="card-header"><span class="card-title">تنظیمات پیشرفته</span></div>
          <div class="card-body">
            <Switch v-model="settings.maintenance_mode" label="حالت تعمیر و نگهداری"
                    hint="بازدیدکنندگان صفحه «به‌زودی برمی‌گردیم» را می‌بینند؛ مدیران به سایت دسترسی دارند." />

            <div class="divider"></div>

            <div class="field">
              <label class="field-label">شناسه Google Analytics</label>
              <input v-model="settings.google_analytics" type="text" class="input input-ltr"
                     placeholder="G-XXXXXXXXXX" maxlength="30">
              <div class="field-hint">
                فقط شناسه اندازه‌گیری را وارد کنید. برای امنیت، کد اسکریپت پذیرفته نمی‌شود.
              </div>
            </div>

            <div class="field mb-0">
              <label class="field-label">تصویر پیش‌فرض اشتراک‌گذاری</label>
              <div class="flex gap-sm">
                <input v-model="settings.seo_meta_image" type="text" class="input input-ltr grow"
                       placeholder="آدرس تصویر">
                <button class="btn btn-secondary" @click="picker = 'seo_meta_image'">
                  <Icon name="image" :size="16" />
                </button>
              </div>
              <div class="field-hint">وقتی نوشته‌ای تصویر شاخص ندارد، این تصویر در شبکه‌های اجتماعی نمایش داده می‌شود.</div>
            </div>
          </div>
        </div>

        <div class="form-actions" style="border:none;padding-top:22px">
          <button class="btn btn-primary" @click="save" :disabled="saving">
            <span v-if="saving" class="spinner"></span>
            <Icon v-else name="save" :size="16" />
            ذخیره تنظیمات
          </button>
        </div>
      </template>

      <MediaPicker v-if="picker" images-only title="انتخاب تصویر"
                   @select="onMediaSelect" @close="picker = null" />
    </div>
  `,
};
