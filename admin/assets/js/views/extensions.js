/**
 * قالب‌ها و افزونه‌ها
 *
 * نصب از فایل زیپ، فعال و غیرفعال کردن، و حذف — همه از داخل پنل.
 * نصب و حذف کدِ اجراشدنی روی سرور را تغییر می‌دهند، پس سرور آن‌ها را
 * فقط برای مدیر کل باز می‌گذارد و پنل هم دکمه‌ها را همان‌طور نشان می‌دهد.
 */

import { Icon } from '../icons.js';
import { LoadingBlock, ConfirmDialog } from '../components/ui.js';
import { api } from '../api.js';
import { store, notify, notifyError } from '../store.js';

const { ref, computed, onMounted } = Vue;

export const ExtensionsView = {
  name: 'ExtensionsView',
  components: { Icon, LoadingBlock, ConfirmDialog },
  setup() {
    const themes = ref([]);
    const plugins = ref([]);
    const activeTheme = ref('default');
    const canInstall = ref(true);
    const maxSize = ref(0);

    const loading = ref(true);
    const busy = ref('');
    const tab = ref('themes');
    const dragging = ref(false);
    const confirmRemove = ref(null);
    const fileInput = ref(null);

    const isAdmin = computed(() => store.user?.role === 'administrator');
    const sizeLabel = computed(() => `${Math.round(maxSize.value / 1024 / 1024)} مگابایت`);

    const apply = (data) => {
      themes.value = data.themes || themes.value;
      plugins.value = data.plugins || plugins.value;

      if (data.active_theme) activeTheme.value = data.active_theme;
    };

    const load = async () => {
      loading.value = true;

      try {
        const data = await api.extensions.list();

        apply(data);
        canInstall.value = data.can_install !== false;
        maxSize.value = data.max_size || 0;
      } catch (error) {
        notifyError(error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(load);

    /* ─── نصب از فایل زیپ ──────────────────────────────────── */
    const install = async (file, overwrite = false) => {
      if (!file) return;

      if (!/\.zip$/i.test(file.name)) {
        notify('فقط فایل زیپ پذیرفته می‌شود', 'error');
        return;
      }

      busy.value = 'install';

      try {
        const data = await api.extensions.install(tab.value === 'themes' ? 'theme' : 'plugin',
                                                  file, overwrite);

        apply(data);
        notify(data.message || 'نصب شد');
      } catch (error) {
        // پیام «قبلاً نصب شده» یعنی فقط تأیید جایگزینی لازم است
        if (!overwrite && /قبلاً نصب شده/.test(error.message || '')) {
          if (window.confirm('این بسته قبلاً نصب شده است. جایگزین شود؟')) {
            await install(file, true);
            return;
          }
        } else {
          notifyError(error);
        }
      } finally {
        busy.value = '';

        if (fileInput.value) fileInput.value.value = '';
      }
    };

    const onDrop = (event) => {
      dragging.value = false;
      install(event.dataTransfer?.files?.[0]);
    };

    /* ─── فعال، غیرفعال، حذف ───────────────────────────────── */
    const run = async (kind, slug, action) => {
      busy.value = `${kind}:${slug}`;

      try {
        const data = await api.extensions.action(kind, slug, action);

        apply(data);
        notify(data.message || 'انجام شد');

        // قالب فعال روی نمایش سایت اثر دارد، نه روی پنل؛ نیازی به
        // بازخوانی صفحه نیست
      } catch (error) {
        notifyError(error);
      } finally {
        busy.value = '';
        confirmRemove.value = null;
      }
    };

    /** نام خوانای افزونه‌ای که قالب به آن نیاز دارد */
    const pluginName = (slug) => plugins.value.find((p) => p.slug === slug)?.name || slug;

    return {
      themes, plugins, activeTheme, canInstall, sizeLabel, loading, busy, tab,
      dragging, confirmRemove, fileInput, isAdmin,
      install, onDrop, run, pluginName,
    };
  },
  template: `
    <div>
      <div class="toolbar">
        <div class="status-filters">
          <button :class="['status-filter', { active: tab === 'themes' }]" @click="tab = 'themes'">
            قالب‌ها <span class="small faint">{{ themes.length }}</span>
          </button>
          <button :class="['status-filter', { active: tab === 'plugins' }]" @click="tab = 'plugins'">
            افزونه‌ها <span class="small faint">{{ plugins.length }}</span>
          </button>
        </div>
      </div>

      <LoadingBlock v-if="loading" />

      <template v-else>
        <!-- نصب از زیپ -->
        <div class="card" v-if="isAdmin">
          <div class="card-header">
            <span class="card-title">
              نصب {{ tab === 'themes' ? 'قالب' : 'افزونه' }} از فایل زیپ
            </span>
          </div>
          <div class="card-body">
            <div v-if="!canInstall" class="small" style="color:var(--warning)">
              نصب از پنل روی این هاست ممکن نیست: یا افزونه zip در PHP فعال نیست
              یا پوشه‌های themes/ و plugins/ قابل نوشتن نیستند. بسته را با FTP
              آپلود کنید؛ بقیه امکانات این صفحه کار می‌کنند.
            </div>

            <div v-else class="dropzone" :class="{ dragging }"
                 @click="fileInput?.click()"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="onDrop">
              <span v-if="busy === 'install'" class="spinner"></span>
              <Icon v-else name="upload" :size="26" style="margin-bottom:8px" />
              <div class="small">
                {{ busy === 'install' ? 'در حال نصب…' : 'فایل زیپ را اینجا رها کنید یا کلیک کنید' }}
              </div>
              <div class="small faint">حداکثر {{ sizeLabel }}</div>
            </div>

            <input ref="fileInput" type="file" accept=".zip,application/zip" class="hidden"
                   @change="install($event.target.files[0])">

            <div class="field-hint">
              بسته باید یک پوشه اصلی داشته باشد که همه فایل‌ها داخل آن باشند.
              فقط بسته‌ای را نصب کنید که به منبعش اعتماد دارید؛ کد آن روی سرور شما اجرا می‌شود.
            </div>
          </div>
        </div>

        <!-- قالب‌ها -->
        <div class="card" v-if="tab === 'themes'">
          <div class="card-header"><span class="card-title">قالب‌های نصب‌شده</span></div>
          <div class="card-body">
            <div class="theme-grid">
              <div v-for="item in themes" :key="item.slug"
                   :class="['theme-card', { active: item.slug === activeTheme }]">
                <span class="theme-shot">
                  <img v-if="item.screenshot" :src="item.screenshot" :alt="item.name" loading="lazy">
                  <Icon v-else name="image" :size="26" />
                </span>

                <span class="theme-meta">
                  <span class="theme-name">
                    {{ item.name }}
                    <span v-if="item.slug === activeTheme" class="badge badge-success">فعال</span>
                  </span>
                  <span v-if="item.description" class="theme-desc">{{ item.description }}</span>
                  <span class="theme-sub" dir="ltr">
                    {{ item.slug }}<template v-if="item.version"> · {{ item.version }}</template>
                  </span>

                  <span v-if="item.missing_plugins?.length" class="small" style="color:var(--warning)">
                    برای کامل کار کردن به این افزونه نیاز دارد:
                    {{ item.missing_plugins.map(pluginName).join('، ') }}
                  </span>
                </span>

                <div class="flex gap-sm">
                  <button v-if="item.slug !== activeTheme" class="btn btn-sm btn-primary"
                          :disabled="busy === 'theme:' + item.slug"
                          @click="run('theme', item.slug, 'activate')">فعال کردن</button>

                  <button v-if="isAdmin && item.slug !== activeTheme && item.slug !== 'default'"
                          class="btn btn-sm btn-ghost"
                          @click="confirmRemove = { kind: 'theme', slug: item.slug, name: item.name }">
                    <Icon name="trash" :size="14" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- افزونه‌ها -->
        <div class="card" v-else>
          <div class="card-header"><span class="card-title">افزونه‌های نصب‌شده</span></div>
          <div class="card-body">
            <p class="small faint mb-0" v-if="!plugins.length">
              هنوز افزونه‌ای نصب نشده است.
            </p>

            <div v-else class="flex-col gap-sm">
              <div v-for="item in plugins" :key="item.slug" class="simple-item">
                <div style="min-width:0">
                  <div class="theme-name">
                    {{ item.name }}
                    <span v-if="item.active" class="badge badge-success">فعال</span>
                  </div>
                  <div v-if="item.description" class="theme-desc">{{ item.description }}</div>
                  <div class="theme-sub" dir="ltr">
                    {{ item.slug }}<template v-if="item.version"> · {{ item.version }}</template>
                  </div>
                </div>

                <div class="flex gap-sm">
                  <button class="btn btn-sm"
                          :class="item.active ? 'btn-ghost' : 'btn-primary'"
                          :disabled="busy === 'plugin:' + item.slug"
                          @click="run('plugin', item.slug, item.active ? 'deactivate' : 'activate')">
                    {{ item.active ? 'غیرفعال کردن' : 'فعال کردن' }}
                  </button>

                  <button v-if="isAdmin && !item.active" class="btn btn-sm btn-ghost"
                          @click="confirmRemove = { kind: 'plugin', slug: item.slug, name: item.name }">
                    <Icon name="trash" :size="14" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <ConfirmDialog v-if="confirmRemove"
                     title="حذف بسته"
                     :message="'«' + confirmRemove.name + '» و تمام فایل‌هایش از سرور پاک می‌شوند. این کار برگشت‌پذیر نیست.'"
                     confirm-label="حذف کن"
                     danger
                     @confirm="run(confirmRemove.kind, confirmRemove.slug, 'delete')"
                     @cancel="confirmRemove = null" />
    </div>
  `,
};
