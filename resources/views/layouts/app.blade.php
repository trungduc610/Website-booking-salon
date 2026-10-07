<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tài khoản') · GlowBook</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Đến nội dung chính</a>
<header class="site-header">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">G</span> GlowBook<span class="brand-dot">.</span></a>
    <nav aria-label="Điều hướng tài khoản">
        @auth
            <form action="{{ route('logout') }}" method="post" class="inline-form">@csrf<button class="btn btn-outline-dark btn-sm">Đăng xuất</button></form>
        @else
            <span class="nav-hint">Một chút chăm sóc cho riêng bạn.</span>
        @endauth
    </nav>
</header>
<main id="main" class="page-container">
    @if(session('success'))
        <div class="notice notice-success" role="status">{{ session('success') }}</div>
    @endif
    @yield('content')
</main>
<footer class="site-footer"><span>GlowBook · Đặt lịch làm đẹp</span><span>Thời gian của bạn, trải nghiệm của bạn.</span></footer>
</body>
</html>
