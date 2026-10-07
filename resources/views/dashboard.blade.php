@extends('layouts.app')
@section('title', 'Tài khoản của bạn')
@section('content')
<section class="dashboard-card">
    <span class="eyebrow">TÀI KHOẢN CỦA BẠN</span>
    <h1>Xin chào, {{ auth()->user()->full_name }}.</h1>
    <p>Bạn đã đăng nhập thành công vào GlowBook.</p>
    <p><a href="{{ route('salons.index') }}">Đặt lịch làm đẹp</a> · <a href="{{ route('bookings.index') }}">Lịch hẹn của bạn</a></p>
    <a href="{{ route('salon.branches') }}">Chi nhánh được phân quyền →</a>
    <dl class="profile-facts"><div><dt>Email</dt><dd>{{ auth()->user()->email }}</dd></div><div><dt>Số điện thoại</dt><dd>{{ auth()->user()->phone ?: 'Chưa cập nhật' }}</dd></div></dl>
    @can('view-platform')<a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Trang quản trị</a>@endcan
</section>
@endsection
