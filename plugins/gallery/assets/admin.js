/**
 * پنل مدیریتِ افزونه گالری و ویدیو
 *
 * این فایل یک اسکریپت ساده است، نه ماژول: پنل مدیریت آن را پیش از
 * راه‌اندازی خودش تزریق می‌کند و Vue به‌صورت سراسری در دسترس است.
 *
 * تنها قرارداد با هسته، window.FardCMS.registerEditorPanel است. کامپوننت
 * یک prop به نام ctx می‌گیرد و فیلدهای خودش را مستقیم روی ctx.meta
 * می‌نویسد؛ ذخیره‌سازی و اعتبارسنجی سمت سرور کار افزونه است، نه پنل.
 */

(function () {
  'use strict';

  if (!window.FardCMS || !window.Vue) {
    return;
  }

  const { ref, computed, watch } = window.Vue;

  /** خواندن و نوشتن یک کلید از meta با مقدار پیش‌فرض */
  function metaField(ctx, key, fallback = '') {
    return computed({
      get: () => ctx.meta[key] ?? fallback,
      set: (value) => { ctx.meta[key] = value; },
    });
  }

  const GalleryPanel = {
    name: 'GalleryPanel',
    props: { ctx: { type: Object, required: true } },
    setup(props) {
      // شیء کامل رسانه‌ها از پاسخ سرور می‌آید تا پیش‌نمایش داشته باشیم؛
      // در ذخیره فقط شناسه‌ها برمی‌گردند
      const items = ref([]);
      const picker = ref(false);

      const sync = () => {
        props.ctx.meta.gallery = items.value.map((item) => item.id);
      };

      watch(() => props.ctx.payload, (payload) => {
        items.value = (payload && payload.gallery) || [];
      }, { immediate: true });

      const onSelect = (chosen) => {
        picker.value = false;

        const existing = new Set(items.value.map((item) => item.id));

        items.value = items.value.concat(chosen.filter((item) => !existing.has(item.id)));
        sync();
      };

      const remove = (index) => {
        items.value.splice(index, 1);
        sync();
      };

      /** جابه‌جایی یک تصویر در ترتیب گالری */
      const move = (index, step) => {
        const target = index + step;

        if (target < 0 || target >= items.value.length) return;

        const [item] = items.value.splice(index, 1);
        items.value.splice(target, 0, item);
        sync();
      };

      return { items, picker, onSelect, remove, move };
    },
    template: `
      <div>
        <div v-if="items.length" class="gallery-thumbs">
          <div v-for="(item, index) in items" :key="item.id" class="gallery-thumb">
            <img :src="item.thumbnail_url || item.url" :alt="item.alt_text || ''" loading="lazy">
            <div class="gallery-thumb-bar">
              <button type="button" @click="move(index, -1)" :disabled="index === 0"
                      title="جابه‌جایی به عقب">‹</button>
              <button type="button" @click="remove(index)" title="حذف از گالری">×</button>
              <button type="button" @click="move(index, 1)" :disabled="index === items.length - 1"
                      title="جابه‌جایی به جلو">›</button>
            </div>
          </div>
        </div>

        <button class="btn btn-ghost btn-block mt-sm" type="button" @click="picker = true">
          افزودن تصویر
        </button>

        <div class="field-hint">
          ترتیب همین‌جا تعیین می‌شود و قالب سایت به همین ترتیب نمایش می‌دهد.
        </div>

        <fardcms-media-picker v-if="picker" multiple images-only title="افزودن به گالری"
                              @select="onSelect" @close="picker = false" />
      </div>
    `,
  };

  const VideoPanel = {
    name: 'VideoPanel',
    props: { ctx: { type: Object, required: true } },
    setup(props) {
      return {
        provider: metaField(props.ctx, 'video_provider'),
        aparat: metaField(props.ctx, 'video_aparat'),
        file: metaField(props.ctx, 'video_file'),
        poster: metaField(props.ctx, 'video_poster'),
      };
    },
    template: `
      <div>
        <div class="field">
          <label class="field-label">محل نگهداری ویدیو</label>
          <select v-model="provider" class="select">
            <option value="">بدون ویدیو</option>
            <option value="aparat">آپارات</option>
            <option value="file">فایل آپلودشده</option>
          </select>
        </div>

        <div class="field" v-if="provider === 'aparat'">
          <label class="field-label">نشانی یا شناسه ویدیوی آپارات</label>
          <input v-model="aparat" type="text" class="input" dir="ltr"
                 placeholder="https://www.aparat.com/v/XXXXX">
          <div class="field-hint">
            نشانی صفحه ویدیو را بچسبانید؛ شناسه‌اش خودکار بیرون کشیده می‌شود.
          </div>
        </div>

        <div class="field" v-if="provider === 'file'">
          <label class="field-label">نشانی فایل ویدیو</label>
          <input v-model="file" type="text" class="input" dir="ltr"
                 placeholder="uploads/1404/06/clip.mp4">
          <div class="field-hint">
            برای فیلم‌های بلند آپارات مناسب‌تر است؛ هاست اشتراکی پهنای باند کمی دارد.
          </div>
        </div>

        <div class="field mb-0" v-if="provider">
          <label class="field-label">تصویر پیش‌نمایش ویدیو</label>
          <input v-model="poster" type="text" class="input" dir="ltr"
                 placeholder="نشانی تصویر">
        </div>
      </div>
    `,
  };

  const DetailsPanel = {
    name: 'DetailsPanel',
    props: { ctx: { type: Object, required: true } },
    setup(props) {
      return {
        subtitle: metaField(props.ctx, 'subtitle'),
        location: metaField(props.ctx, 'shoot_location'),
      };
    },
    template: `
      <div>
        <div class="field">
          <label class="field-label">زیرعنوان</label>
          <input v-model="subtitle" type="text" class="input" maxlength="200"
                 placeholder="مثلاً: عکاسی عروسی سارا و امیر">
        </div>

        <div class="field mb-0">
          <label class="field-label">محل عکاسی</label>
          <input v-model="location" type="text" class="input" maxlength="200"
                 placeholder="مثلاً: باغ ارم، شیراز">
        </div>
      </div>
    `,
  };

  window.FardCMS.registerEditorPanel({ id: 'gallery', title: 'گالری تصاویر', component: GalleryPanel });
  window.FardCMS.registerEditorPanel({ id: 'gallery-video', title: 'ویدیو', component: VideoPanel });
  window.FardCMS.registerEditorPanel({ id: 'gallery-details', title: 'اطلاعات تکمیلی', component: DetailsPanel });
})();
