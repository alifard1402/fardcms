/**
 * مجموعه آیکون‌های SVG
 *
 * هر آیکون فقط مسیر (path) خود را نگه می‌دارد و کامپوننت Icon آن را
 * داخل یک <svg> استاندارد رندر می‌کند.
 */

const paths = {
  dashboard:  '<path d="M4 13h6V4H4v9zm0 7h6v-5H4v5zm10 0h6V11h-6v9zm0-16v5h6V4h-6z"/>',
  posts:      '<path d="M4 6h16M4 12h16M4 18h10"/>',
  page:       '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/><path d="M14 3v5h5"/>',
  media:      '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-6 6"/>',
  comment:    '<path d="M21 12a8 8 0 01-8 8H8l-5 3v-4.5A8 8 0 0113 4a8 8 0 018 8z"/>',
  tag:        '<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0l-7.2-7.2A2 2 0 013 12V5a2 2 0 012-2h7a2 2 0 011.4.6l7.2 7.2a2 2 0 010 2.6z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
  folder:     '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
  users:      '<path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8"/>',
  menu:       '<path d="M4 6h16M4 12h16M4 18h16"/>',
  settings:   '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.6 1.7 1.7 0 00-1.9.4l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.6-1.1 1.7 1.7 0 00-.4-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H11a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V11a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
  plus:       '<path d="M12 5v14M5 12h14"/>',
  search:     '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  edit:       '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
  trash:      '<path d="M3 6h18M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>',
  eye:        '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
  check:      '<path d="M20 6L9 17l-5-5"/>',
  x:          '<path d="M18 6L6 18M6 6l12 12"/>',
  chevronDown:  '<path d="M6 9l6 6 6-6"/>',
  chevronRight: '<path d="M9 18l6-6-6-6"/>',
  chevronLeft:  '<path d="M15 18l-6-6 6-6"/>',
  arrowRight:   '<path d="M5 12h14M13 5l7 7-7 7"/>',
  arrowLeft:    '<path d="M19 12H5M11 19l-7-7 7-7"/>',
  logout:     '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
  user:       '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>',
  lock:       '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
  upload:     '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 9l5-5 5 5M12 4v12"/>',
  image:      '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-6 6"/>',
  file:       '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/><path d="M14 3v5h5"/>',
  video:      '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 10l6-3v10l-6-3z"/>',
  audio:      '<path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/>',
  eyeOff:     '<path d="M17.9 17.9A10 10 0 0112 19c-6.4 0-10-7-10-7a17.6 17.6 0 015.1-5.9m3.2-1A10 10 0 0112 5c6.4 0 10 7 10 7a17.6 17.6 0 01-2.2 3.2M9.9 9.9a3 3 0 104.2 4.2M2 2l20 20"/>',
  chart:      '<path d="M3 3v18h18"/><path d="M7 15l3-4 3 3 5-7"/>',
  clock:      '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
  activity:   '<path d="M22 12h-4l-3 8-4-16-3 8H2"/>',
  alert:      '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
  info:       '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
  sun:        '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  moon:       '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
  external:   '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6M10 14L21 3"/>',
  grip:       '<circle cx="9" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="15" cy="18" r="1.4"/>',
  bold:       '<path d="M7 5h6a4 4 0 010 8H7zM7 13h7a4 4 0 010 8H7z"/>',
  italic:     '<path d="M19 4h-9M14 20H5M15 4L9 20"/>',
  underline:  '<path d="M6 4v7a6 6 0 0012 0V4M4 21h16"/>',
  list:       '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  listOrdered:'<path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M4 16.5A1.5 1.5 0 116 18l-2 2h3"/>',
  quote:      '<path d="M3 21c3 0 7-1 7-8V5H3v7h4c0 4-2 5-4 5zM14 21c3 0 7-1 7-8V5h-7v7h4c0 4-2 5-4 5z"/>',
  code:       '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
  link:       '<path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7L12.3 19"/>',
  heading:    '<path d="M6 4v16M18 4v16M6 12h12"/>',
  refresh:    '<path d="M21 12a9 9 0 11-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
  filter:     '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>',
  copy:       '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
  save:       '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
  key:        '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2M17 6l3 3M14 9l3 3"/>',
  restore:    '<path d="M3 12a9 9 0 103-6.8L3 8"/><path d="M3 3v5h5"/>',
  spam:       '<path d="M10.3 3.2L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L14.7 3.2a2 2 0 00-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
  home:       '<path d="M3 10l9-7 9 7v10a2 2 0 01-2 2H5a2 2 0 01-2-2V10z"/><path d="M9 22V12h6v10"/>',
};

/**
 * کامپوننت نمایش آیکون
 *
 * <Icon name="posts" /> یا <Icon name="posts" :size="20" filled />
 */
export const Icon = {
  name: 'Icon',
  props: {
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
    filled: { type: Boolean, default: false },
  },
  computed: {
    path() {
      return paths[this.name] || '';
    },
  },
  template: `
    <svg :width="size" :height="size" viewBox="0 0 24 24"
         :fill="filled ? 'currentColor' : 'none'"
         :stroke="filled ? 'none' : 'currentColor'"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true" v-html="path"></svg>
  `,
};

export const iconNames = Object.keys(paths);
