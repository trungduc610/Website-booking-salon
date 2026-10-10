<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f5f0">
    <title>@yield('title', 'Tài khoản') · GlowBook</title>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/experience.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('vendor/alpine/alpine.min.js') }}" defer></script>
</head>

<body>
    <a class="skip-link" href="#main">Đến nội dung chính</a>
    <div class="announcement">Một khoảng nghỉ dành riêng cho bạn</div>
    <header class="site-header" x-data="navigation">
        <a class="brand" href="{{ route('salons.index') }}" aria-label="GlowBook — Trang khám phá"><span
                class="brand-mark" aria-hidden="true">g</span> glowbook<span class="brand-dot">.</span></a>
        <nav class="desktop-nav" aria-label="Điều hướng chính">
            <a href="{{ route('salons.index') }}" @if (request()->routeIs('salons.*', 'bookings.create')) aria-current="page" @endif>Khám
                phá</a>
            @auth
                <a href="{{ route('bookings.index') }}" @if (request()->routeIs('bookings.index', 'bookings.show', 'payments.*')) aria-current="page" @endif>Lịch
                    hẹn của tôi</a>
                <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Tài
                    khoản</a>
            @else
                <a href="{{ route('login') }}">Đăng nhập</a>
            @endauth
        </nav>
        <div class="header-actions">
            <button type="button" class="icon-button" data-theme-toggle aria-label="Đổi giao diện sáng tối"
                aria-pressed="false"><x-icon name="theme" /></button>
            @auth
                <form action="{{ route('logout') }}" method="post" class="inline-form desktop-logout">@csrf<button
                        class="btn btn-sm">Đăng xuất</button></form>
            @else
                <a class="btn btn-primary desktop-logout" href="{{ route('register') }}">Bắt đầu</a>
            @endauth
            <button type="button" class="icon-button mobile-menu-button" @click="toggle" :aria-expanded="open"
                aria-controls="mobile-nav" aria-label="Mở điều hướng"><x-icon name="menu" /></button>
        </div>
        <nav id="mobile-nav" class="mobile-nav" x-cloak x-show="open" @keydown.escape.window="close"
            @click.outside="close" aria-label="Điều hướng di động">
            <a href="{{ route('salons.index') }}" @if (request()->routeIs('salons.*', 'bookings.create')) aria-current="page" @endif>Khám phá
                salon</a>
            @auth
                <a href="{{ route('bookings.index') }}" @if (request()->routeIs('bookings.index', 'bookings.show', 'payments.*')) aria-current="page" @endif>Lịch
                    hẹn của tôi</a><a href="{{ route('dashboard') }}"
                    @if (request()->routeIs('dashboard')) aria-current="page" @endif>Tài
                    khoản</a>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="btn">Đăng xuất</button></form>
            @else<a href="{{ route('login') }}">Đăng nhập</a><a href="{{ route('register') }}">Tạo tài
                khoản</a>@endauth
        </nav>
    </header>
    @php($branch = $branch ?? request()->route('branch'))
    @php($operations = request()->is('salon/*') || request()->routeIs('admin.*'))
    <div class="{{ $operations ? 'workspace' : '' }}" @if ($operations) x-data="operations" @endif>
        @if ($operations)
            <aside class="operations-nav">
                <button class="sidebar-heading" @click="toggle" :aria-expanded="expanded"
                    aria-controls="operations-links">Không gian salon <span aria-hidden="true">⌄</span></button>
                <nav id="operations-links" x-show="expanded" aria-label="Quản lý salon">
                    <a href="{{ route('salon.branches') }}"
                        @if (request()->routeIs('salon.branches')) aria-current="page" @endif><x-icon name="branch" /> <span>Chi
                            nhánh</span></a>
                    @if (isset($branch) && $branch)
                        @can('manageBookings', $branch)
                            <a href="{{ route('salon.bookings', $branch) }}"
                                @if (request()->routeIs('salon.bookings')) aria-current="page" @endif><x-icon name="calendar" />
                                <span>Lịch hẹn</span></a>
                        @endcan
                        <a href="{{ route('catalog.index', $branch) }}"
                            @if (request()->routeIs('catalog.*')) aria-current="page" @endif><x-icon name="sparkle" />
                            <span>Dịch vụ</span></a>
                        <a href="{{ route('staff.index', $branch) }}"
                            @if (request()->routeIs('staff.*')) aria-current="page" @endif><x-icon name="users" />
                            <span>Đội ngũ</span></a>
                        @can('update', $branch)
                            <a href="{{ route('schedule.edit', $branch) }}"
                                @if (request()->routeIs('schedule.*')) aria-current="page" @endif><x-icon name="clock" />
                                <span>Giờ hoạt
                                    động</span></a>
                        @endcan
                        @if (auth()->user()->hasRole('PLATFORM_ADMIN') || auth()->user()->hasRole('BUSINESS_OWNER', $branch->business_id))
                            <a href="{{ route('vouchers.index', $branch) }}"
                                @if (request()->routeIs('vouchers.*')) aria-current="page" @endif><x-icon name="tag" />
                                <span>Ưu đãi</span></a>
                        @endif
                    @endif
                    @can('view-platform')
                        <a href="{{ route('admin.dashboard') }}"><x-icon name="branch" /> <span>Quản trị nền
                                tảng</span></a>
                    @endcan
                    <a href="{{ route('salons.index') }}"><x-icon name="external" /> <span>Xem trang khách
                            hàng</span></a>
                </nav>
                <p class="sidebar-note">Một ngày vận hành nhẹ nhàng.<br>Một trải nghiệm đáng nhớ.</p>
            </aside>
        @endif
        <main id="main" class="page-container" tabindex="-1">
            @if (session('success'))
                <div class="notice notice-success" role="status">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <footer class="site-footer"><a class="brand" href="{{ route('salons.index') }}">glowbook.</a><span>Thời gian của
            bạn. Trải nghiệm của bạn.</span><span>Chăm sóc bản thân cùng Glowbook</span></footer>
</body>

</html>
