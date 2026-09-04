/**
 * فرم ایجاد و ویرایش نوشته یا برگه
 */

import { Icon } from '../icons.js';
import { LoadingBlock, Checkbox, Switch, TagInput, ConfirmDialog } from '../components/ui.js';
import { ContentEditor } from '../components/editor.js';
import { FeaturedImagePicker } from '../components/media-picker.js';
import { api } from '../api.js';
import { can, notify, notifyError } from '../store.js';
import { navigate } from '../router.js';

const { ref, computed, watch, onMounted, onBeforeUnmount } = Vue;

export const PostEditView = {
  name: 'PostEditView',
  components: {
    Icon, LoadingBlock, Checkbox, Switch, TagInput, ConfirmDialog,
    ContentEditor, FeaturedImagePicker,
  },
  props: {
    id: { type: [String, Number], default: null },
    type: { type: String, default: 'post' },
  },
  setup(props) {
    const form = ref({
      id: null,
      title: '',
      slug: '',
      content: '',
      excerpt: '',
      type: props.type,
      status: 'draft',
      featured_image: '',
      parent_id: null,
      menu_order: 0,
      comment_status: true,
      published_at: '',
      categories: [],
      tags: [],
    });

    const categories = ref([]);
    const parentOptions = ref([]);
    const loading = ref(true);
    const saving = ref(false);
    const slugEdited = ref(false);
    const dirty = ref(false);
    const confirmLeave = ref(null);
    const errors = ref({});

    const isNew = computed(() => !form.value.id);
    const isPage = computed(() => props.type === 'page');
    const labels = computed(() => (isPage.value
      ? { one: 'برگه', listRoute: '/pages' }
      : { one: 'نوشته', listRoute: '/posts' }));

    /** آدرس عمومی محتوا برای پیش‌نمایش */
    const publicUrl = computed(() => {
      if (!form.value.slug) return '';

      const base = window.location.origin + window.location.pathname.replace(/admin\/.*$/, '');

      return base + (isPage.value ? '' : 'blog/') + form.value.slug;
    });

    /* ─── بارگذاری ─────────────────────────────────────────── */
    const load = async () => {
      loading.value = true;

      try {
        // دسته‌ها فقط برای نوشته‌ها لازم است
        if (!isPage.value && can('edit_posts')) {
          const data = await api.terms.list('category');
          categories.value = data.terms || [];
        }

        // برگه‌ها می‌توانند والد داشته باشند
        if (isPage.value) {
          const data = await api.posts.list({ type: 'page', status: 'any', per_page: 100 });
          parentOptions.value = (data.posts || []).filter((p) => String(p.id) !== String(props.id));
        }

        if (props.id) {
          const data = await api.posts.show(props.id);
          const post = data.post;

          form.value = {
            id: post.id,
            title: post.title,
            slug: post.slug,
            content: post.content || '',
            excerpt: post.excerpt || '',
            type: post.type,
            status: post.status,
            featured_image: post.featured_image || '',
            parent_id: post.parent_id,
            menu_order: post.menu_order || 0,
            comment_status: post.comment_status === 'open',
            published_at: post.published_at ? post.published_at.replace(' ', 'T').slice(0, 16) : '',
            categories: (post.categories || []).map((c) => c.id),
            tags: (post.tags || []).map((t) => t.name),
          };

          slugEdited.value = true;
        }
      } catch (error) {
        notifyError(error);
        navigate(labels.value.listRoute);
      } finally {
        loading.value = false;

        // تغییرات کاربر از این لحظه شمرده می‌شود
        setTimeout(() => { dirty.value = false; }, 60);
      }
    };

    onMounted(load);

    // هر تغییری در فرم، وضعیت «ذخیره‌نشده» را فعال می‌کند
    watch(form, () => { dirty.value = true; }, { deep: true });

    /**
     * هشدار پیش از بستن صفحه در صورت وجود تغییرات ذخیره‌نشده
     */
    const onBeforeUnload = (event) => {
      if (dirty.value) {
        event.preventDefault();
        event.returnValue = '';
      }
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload));

    /* ─── ذخیره ────────────────────────────────────────────── */
    const validate = () => {
      errors.value = {};

      if (!form.value.title.trim()) {
        errors.value.title = 'عنوان الزامی است';
      } else if (form.value.title.length > 250) {
        errors.value.title = 'عنوان نمی‌تواند بیش از ۲۵۰ کاراکتر باشد';
      }

      return Object.keys(errors.value).length === 0;
    };

    const save = async (statusOverride = null) => {
      if (!validate()) {
        notify(Object.values(errors.value)[0], 'error');
        return;
      }

      saving.value = true;

      const payload = { ...form.value, type: props.type };

      if (statusOverride) {
        payload.status = statusOverride;
      }

      try {
        const data = await api.posts.save(payload);
        const post = data.post;

        notify(isNew.value ? `${labels.value.one} ایجاد شد` : 'تغییرات ذخیره شد');

        // نامک ممکن است سمت سرور یکتا شده باشد
        form.value.id = post.id;
        form.value.slug = post.slug;
        form.value.status = post.status;
        dirty.value = false;

        // پس از ایجاد، آدرس صفحه به حالت ویرایش تغییر می‌کند
        if (isNew.value || String(props.id) !== String(post.id)) {
          navigate(`${labels.value.listRoute}/${post.id}`, { replace: true });
        }
      } catch (error) {
        notifyError(error);
      } finally {
        saving.value = false;
      }
    };

    /** بازگشت به فهرست با هشدار در صورت تغییرات ذخیره‌نشده */
    const goBack = () => {
      if (dirty.value) {
        confirmLeave.value = true;
        return;
      }

      navigate(labels.value.listRoute);
    };

    /* ─── نامک خودکار ──────────────────────────────────────── */
    const suggestSlug = () => {
      // نامک تا وقتی کاربر دستی تغییرش نداده از عنوان ساخته می‌شود
      if (slugEdited.value || !form.value.title.trim()) return;

      form.value.slug = form.value.title
        .trim()
        .replace(/[يك]/g, (ch) => ({ 'ي': 'ی', 'ك': 'ک' }[ch]))
        .replace(/[\s‌_]+/g, '-')
        .replace(/[^\p{L}\p{N}-]+/gu, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase()
        .slice(0, 100);
    };

    const toggleCategory = (id) => {
      const index = form.value.categories.indexOf(id);

      if (index === -1) {
        form.value.categories.push(id);
      } else {
        form.value.categories.splice(index, 1);
      }
    };

    const statusOptions = computed(() => {
      const options = [
        { value: 'draft', label: 'پیش‌نویس' },
        { value: 'pending', label: 'در انتظار بازبینی' },
      ];

      // فقط دارندگان دسترسی انتشار می‌توانند وضعیت منتشرشده را انتخاب کنند
      if (can('publish_posts')) {
        options.unshift({ value: 'publish', label: 'منتشرشده' });
        options.push({ value: 'private', label: 'خصوصی' });
      }

      return options;
    });

    return {
      form, categories, parentOptions, loading, saving, dirty, errors,
      confirmLeave, isNew, isPage, labels, publicUrl, statusOptions, slugEdited,
      save, goBack, suggestSlug, toggleCategory, can, navigate,
    };
  },
  template: `
    <div>
      <LoadingBlock v-if="loading" />

      <template v-else>
        <div class="toolbar">
          <button class="btn btn-ghost" @click="goBack">
            <Icon name="arrowRight" :size="16" /> {{ labels.one }}‌ها
          </button>

          <div class="toolbar-spacer"></div>

          <span class="badge badge-warning" v-if="dirty">تغییرات ذخیره‌نشده</span>

          <a class="btn btn-secondary" v-if="!isNew && form.status === 'publish' && publicUrl"
             :href="publicUrl" target="_blank" rel="noopener">
            <Icon name="external" :size="16" /> مشاهده
          </a>

          <button class="btn btn-secondary" @click="save('draft')" :disabled="saving"
                  v-if="form.status !== 'publish'">
            ذخیره پیش‌نویس
          </button>

          <button class="btn btn-primary" @click="save()" :disabled="saving">
            <span v-if="saving" class="spinner"></span>
            <Icon v-else name="save" :size="16" />
            {{ form.status === 'publish' ? 'به‌روزرسانی' : 'ذخیره' }}
          </button>
        </div>

        <div class="split">
          <!-- ستون اصلی -->
          <div class="stack">
            <div class="card">
              <div class="card-body">
                <div class="field">
                  <input v-model="form.title" type="text"
                         :class="['input', { 'has-error': errors.title }]"
                         :placeholder="'عنوان ' + labels.one"
                         style="font-size:19px;font-weight:600;padding:15px 17px"
                         @input="suggestSlug">
                  <div class="field-error" v-if="errors.title">{{ errors.title }}</div>
                </div>

                <div class="field mb-0">
                  <label class="field-label">نامک (بخش آدرس)</label>
                  <input v-model="form.slug" type="text" class="input input-auto"
                         placeholder="my-post-slug" @input="slugEdited = true">
                  <div class="field-hint" v-if="publicUrl">
                    آدرس نهایی: <span class="slug-text">{{ publicUrl }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">محتوا</span></div>
              <div class="card-body">
                <ContentEditor v-model="form.content" />
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <span class="card-title">خلاصه</span>
                <span class="small faint">اختیاری — در فهرست‌ها و نتایج جستجو نمایش داده می‌شود</span>
              </div>
              <div class="card-body">
                <textarea v-model="form.excerpt" class="textarea" maxlength="500" rows="3"
                          placeholder="اگر خالی بماند، به‌صورت خودکار از ابتدای محتوا ساخته می‌شود."></textarea>
                <div class="field-hint">{{ (form.excerpt || '').length.toLocaleString('fa-IR') }} از ۵۰۰ کاراکتر</div>
              </div>
            </div>
          </div>

          <!-- ستون کنار -->
          <div class="stack">
            <div class="card">
              <div class="card-header"><span class="card-title">انتشار</span></div>
              <div class="card-body">
                <div class="field">
                  <label class="field-label">وضعیت</label>
                  <select v-model="form.status" class="select">
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                  <div class="field-hint" v-if="!can('publish_posts')">
                    برای انتشار مستقیم به دسترسی انتشار نیاز دارید؛ نوشته پس از بازبینی منتشر می‌شود.
                  </div>
                </div>

                <div class="field mb-0">
                  <label class="field-label">زمان انتشار</label>
                  <input v-model="form.published_at" type="datetime-local" class="input">
                  <div class="field-hint">برای انتشار در آینده، تاریخ بعدی را انتخاب کنید.</div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">تصویر شاخص</span></div>
              <div class="card-body">
                <FeaturedImagePicker v-model="form.featured_image" />
              </div>
            </div>

            <!-- دسته‌ها و برچسب‌ها فقط برای نوشته -->
            <template v-if="!isPage">
              <div class="card">
                <div class="card-header">
                  <span class="card-title">دسته‌بندی‌ها</span>
                  <button class="btn btn-sm btn-ghost" @click="navigate('/categories')" title="مدیریت دسته‌ها">
                    <Icon name="settings" :size="14" />
                  </button>
                </div>
                <div class="card-body">
                  <p class="small faint mb-0" v-if="!categories.length">
                    هنوز دسته‌ای ساخته نشده است.
                  </p>
                  <div class="flex-col gap-sm" style="max-height:220px;overflow-y:auto" v-else>
                    <Checkbox v-for="cat in categories" :key="cat.id"
                              :model-value="form.categories.includes(cat.id)"
                              :label="cat.name"
                              @update:model-value="toggleCategory(cat.id)" />
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header"><span class="card-title">برچسب‌ها</span></div>
                <div class="card-body">
                  <TagInput v-model="form.tags" />
                  <div class="field-hint">برچسب‌های جدید به‌صورت خودکار ساخته می‌شوند.</div>
                </div>
              </div>
            </template>

            <!-- تنظیمات برگه -->
            <div class="card" v-if="isPage">
              <div class="card-header"><span class="card-title">ویژگی‌های برگه</span></div>
              <div class="card-body">
                <div class="field">
                  <label class="field-label">برگه والد</label>
                  <select v-model="form.parent_id" class="select">
                    <option :value="null">— بدون والد —</option>
                    <option v-for="page in parentOptions" :key="page.id" :value="page.id">
                      {{ page.title }}
                    </option>
                  </select>
                </div>
                <div class="field mb-0">
                  <label class="field-label">ترتیب نمایش</label>
                  <input v-model.number="form.menu_order" type="number" class="input" min="0">
                  <div class="field-hint">عدد کوچک‌تر، بالاتر نمایش داده می‌شود.</div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">گفت‌وگو</span></div>
              <div class="card-body">
                <Switch v-model="form.comment_status" label="اجازه ارسال دیدگاه" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <ConfirmDialog v-if="confirmLeave"
                     title="تغییرات ذخیره‌نشده"
                     message="تغییرات شما ذخیره نشده است. اگر خارج شوید، این تغییرات از دست می‌رود."
                     confirm-label="خروج بدون ذخیره"
                     danger
                     @confirm="navigate(labels.listRoute)"
                     @cancel="confirmLeave = null" />
    </div>
  `,
};
