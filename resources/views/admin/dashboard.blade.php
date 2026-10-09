@extends('layouts.app')
@section('title', 'Quản trị')
@section('content')
    <div class="section-heading">
        <div><span class="eyebrow">GLOWBOOK / PLATFORM OVERVIEW</span>
            <h1>Mọi trải nghiệm, một nơi.</h1>
            <p class="muted">Tổng quan vận hành trên toàn nền tảng.</p>
        </div>
    </div>
    <div class="metric-grid">
        <dl class="metric-card">
            <dt>Chi nhánh hoạt động</dt>
            <dd>{{ number_format($metrics['branches']) }}</dd><dd class="metric-caption"><small>Đang hoạt động trên nền tảng</small></dd></dl>
        <dl class="metric-card">
            <dt>Lịch hẹn đang xử lý</dt>
            <dd>{{ number_format($metrics['bookings']) }}</dd><dd class="metric-caption"><small>Không tính giữ chỗ đã hết hạn</small></dd></dl>
        <dl class="metric-card">
            <dt>Tài khoản hoạt động</dt>
            <dd>{{ number_format($metrics['users']) }}</dd><dd class="metric-caption"><small>Tài khoản có quyền đăng nhập</small></dd></dl>
    </div>
    <section class="dashboard-card"><span class="eyebrow">YOUR OPERATIONS DESK</span>
        <h2>Chăm sóc trải nghiệm từ bên trong.</h2>
        <p>Chọn chi nhánh để xem lịch hẹn, quản lý đội ngũ, dịch vụ và ưu đãi.</p>
        <div class="flex flex-wrap gap-3 mt-6"><a class="btn btn-primary" href="{{ route('salon.branches') }}">Quản lý chi
                nhánh ↗</a><a class="btn" href="{{ route('salons.index') }}">Khám phá trang khách hàng ↗</a></div>
    </section>
@endsection
