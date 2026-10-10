@props(['name'])
<svg {{ $attributes->class(['ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5" />
            <path d="m16 16 4.5 4.5" />
        @break

        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
        @break

        @case('theme')
            <circle cx="12" cy="12" r="8" />
            <path d="M12 4v16M12 4a8 8 0 0 1 0 16" fill="currentColor" />
        @break

        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="3" />
            <path d="M7 3v4m10-4v4M3 11h18m-13 5h3" />
        @break

        @case('location')
            <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z" />
            <circle cx="12" cy="10" r="2.5" />
        @break

        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        @break

        @case('users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3 21v-2a6 6 0 0 1 12 0v2m1-16a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2" />
        @break

        @case('branch')
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <path d="M9 21v-5h6v5M8 7h1m6 0h1M8 11h1m6 0h1" />
        @break

        @case('tag')
            <path d="m3 12 9 9 9-9V3h-9L3 12Z" />
            <circle cx="16" cy="8" r="1" />
        @break

        @case('sparkle')
            <path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z" />
        @break

        @case('arrow')
            <path d="M5 12h14m-6-6 6 6-6 6" />
        @break

        @case('external')
            <path d="M7 17 17 7M7 7h10v10" />
        @break

        @case('check')
            <path d="m5 12 4 4L19 6" />
        @break
    @endswitch
</svg>
