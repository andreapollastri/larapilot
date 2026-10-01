{{-- One stroke icon, drawn on a 24px grid. Inherits the text color. --}}
<svg class="icon {{ $class ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name ?? '')
    @case('board')
        <rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="9.5" y="4" width="5" height="10" rx="1.5"/><rect x="16" y="4" width="5" height="13" rx="1.5"/>
        @break
    @case('prd')
        <path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>
        @break
    @case('inception')
        <circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>
        @break
    @case('plan')
        <path d="M3 4v16"/><rect x="6" y="5" width="9" height="3.5" rx="1"/><rect x="9" y="10.25" width="11" height="3.5" rx="1"/><rect x="6" y="15.5" width="7" height="3.5" rx="1"/>
        @break
    @case('design')
        <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>
        @break
    @case('settings')
        <path d="M4 7h9M19 7h1M4 17h1M11 17h9"/><circle cx="16" cy="7" r="2.5"/><circle cx="8" cy="17" r="2.5"/>
        @break
    @case('skills')
        <path d="m11 3 1.9 5.1L18 10l-5.1 1.9L11 17l-1.9-5.1L4 10l5.1-1.9z"/><path d="M19 15v5M16.5 17.5h5"/>
        @break
    @case('files')
    @case('folder')
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        @break
    @case('folder-open')
        <path d="M3 8V7a2 2 0 0 1 2-2h4l2 2.5h6a2 2 0 0 1 2 2V10"/><path d="M3.5 19h13.8a2 2 0 0 0 1.9-1.4L21 12a1.5 1.5 0 0 0-1.4-2H7.3a2 2 0 0 0-1.9 1.4L3 18.3a.6.6 0 0 0 .5.7z"/>
        @break
    @case('folder-plus')
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M12 10.5v5M9.5 13h5"/>
        @break
    @case('database')
        <ellipse cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v13c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-13"/><path d="M4.5 12c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5"/>
        @break
    @case('table')
        <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M3 15h18M9 9.5V20"/>
        @break
    @case('view')
        <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.75"/>
        @break
    @case('key')
        <circle cx="8" cy="15" r="4"/><path d="m11 12 8.5-8.5M16 7l2.5 2.5M14 9l2 2"/>
        @break
    @case('git')
        <circle cx="6" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="8" r="2"/><path d="M6 7v10"/><path d="M18 10a6 6 0 0 1-6 6H8"/>
        @break
    @case('usage')
        <path d="M3 12h4l2.5-7 5 14 2.5-7h4"/>
        @break
    @case('economics')
        <path d="M4 20h16"/><path d="m5 15 4.5-4.5 3.5 3L19 7"/><path d="M15 7h4v4"/>
        @break
    @case('api')
        <path d="M8.5 7 4 12l4.5 5M15.5 7 20 12l-4.5 5"/>
        @break
    @case('docs')
        <path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19"/>
        @break
    @case('menu')
        <path d="M4 7h16M4 12h16M4 17h16"/>
        @break
    @case('close')
        <path d="M6 6l12 12M18 6 6 18"/>
        @break
    @case('sun')
        <circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/>
        @break
    @case('moon')
        <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>
        @break
    @case('auto')
        <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M9 20h6M12 16v4"/>
        @break
    @case('upload')
        <path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
        @break
    @case('download')
        <path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
        @break
    @case('file')
        <path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/>
        @break
    @case('image')
        <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
        @break
    @case('archive')
        <rect x="3" y="4" width="18" height="5" rx="1.5"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>
        @break
    @case('link')
        <path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>
        @break
    @case('chevron')
        <path d="m9 6 6 6-6 6"/>
        @break
    @case('back')
        <path d="M19 12H5M11 6l-6 6 6 6"/>
        @break
    @case('pencil')
        <path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>
        @break
    @case('trash')
        <path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
        @break
    @case('external')
        <path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>
        @break
    @case('search')
        <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
        @break
    @case('lock')
        <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
        @break
    @case('check')
        <path d="m5 12.5 4.5 4.5L19 7.5"/>
        @break
    @case('chevron-left')
        <path d="m15 6-6 6 6 6"/>
        @break
    @case('plus')
        <path d="M12 5v14M5 12h14"/>
        @break
    @case('minus')
        <path d="M5 12h14"/>
        @break
    @case('expand')
        <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>
        @break
    @case('page-single')
        <rect x="6" y="3" width="12" height="18" rx="1.5"/><path d="M9.5 8h5M9.5 12h5M9.5 16h3"/>
        @break
    @case('page-dual')
        <rect x="2.5" y="4" width="8.5" height="16" rx="1.5"/><rect x="13" y="4" width="8.5" height="16" rx="1.5"/>
        @break
    @case('grid')
        <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
        @break
    @case('tag')
        <path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-8 8z"/><circle cx="7.8" cy="7.8" r="1.3"/>
        @break
    @case('merge')
        <circle cx="6" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="12" r="2"/><path d="M6 7v10"/><path d="M6 7c0 4 4 5 10 5"/>
        @break
    @case('shield')
        <path d="M12 3 4.5 6v5.5c0 4.6 3 8.2 7.5 9.5 4.5-1.3 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.2 10"/>
        @break
    @case('bug')
        <rect x="8" y="7" width="8" height="12" rx="4"/><path d="M12 11v8M9.5 4.5 11 7M14.5 4.5 13 7M8 11H4.5M8 15H4M16 11h3.5M16 15h4"/>
        @break
    @case('refresh')
        <path d="M20 11a8 8 0 0 0-14.5-4.2M4 4.5V8h3.5"/><path d="M4 13a8 8 0 0 0 14.5 4.2M20 19.5V16h-3.5"/>
        @break
    @case('info')
        <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.6v.2"/>
        @break
    @case('package')
        <path d="M12 3 4 7v10l8 4 8-4V7z"/><path d="m4 7 8 4 8-4M12 11v10"/><path d="m8 5 8 4"/>
        @break
    @case('about')
        <path d="m12 3 8.5 4.5L12 12 3.5 7.5z"/><path d="m3.5 12 8.5 4.5 8.5-4.5"/><path d="m3.5 16.5 8.5 4.5 8.5-4.5"/>
        @break
    @default
        <circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
