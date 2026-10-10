@extends('layouts.app')
@section('title', 'Tài khoản của bạn')
@section('content')
    <section class="welcome-panel">
        <div><span class="eyebrow">Không gian của bạn</span>
            <h1>Xin chào,<br><em>{{ auth()->user()->full_name }}.</em></h1>
            <p>Một khoảng thời gian dành riêng cho bạn đang chờ.</p><a class="btn btn-primary"
                href="{{ route('salons.index') }}">Khám phá & đặt lịch ↗</a>
        </div><span class="welcome-flower" aria-hidden="true">✳</span>
    </section>
    @if ($upcoming)
        <section class="mb-6"><span class="eyebrow">CUỘC HẸN SẮP TỚI</span>
            <article class="appointment-ticket">
                <div class="ticket-date">
                    <strong>{{ \Carbon\CarbonImmutable::parse($upcoming->appointment_date)->format('d') }}</strong>{{ \Carbon\CarbonImmutable::parse($upcoming->appointment_date)->format('m / Y') }}
                </div>
                <div class="ticket-info"><span
                        class="status-badge status-{{ $upcoming->status }}">{{ __('booking.statuses.' . $upcoming->status) }}</span>
                    <h2>{{ $upcoming->branch->name }}</h2>
                    <p>{{ substr($upcoming->appointment_start_time, 0, 5) }} · {{ $upcoming->branch->timezone }}</p>
                </div><a class="btn" href="{{ route('bookings.show', $upcoming) }}">Xem cuộc hẹn ↗</a>
            </article>
        </section>
    @endif
    <div class="portal-grid">
        <a class="portal-card" href="{{ route('bookings.index') }}"><span class="portal-icon" aria-hidden="true"><x-icon
                    name="calendar" /></span><span class="eyebrow">Cuộc hẹn</span>
            <h2>Lịch hẹn của tôi</h2>
            <p>Xem thông tin, trạng thái và quản lý các cuộc hẹn.</p><span class="card-link">Xem lịch hẹn <span
                    aria-hidden="true">↗</span></span>
        </a><a class="portal-card" href="{{ route('salons.index') }}"><span class="portal-icon" aria-hidden="true"><x-icon
                    name="sparkle" /></span><span class="eyebrow">Khám phá</span>
            <h2>Chăm sóc bản thân</h2>
            <p>Chọn một không gian và dịch vụ dành cho bạn.</p><span class="card-link">Khám phá salon <span
                    aria-hidden="true">↗</span></span>
        </a><a class="portal-card" href="{{ route('salon.branches') }}"><span class="portal-icon"
                aria-hidden="true"><x-icon name="branch" /></span><span class="eyebrow">Quản lý salon</span>
            <h2>Không gian salon</h2>
            <p>Truy cập chi nhánh và công việc được phân quyền.</p><span class="card-link">Quản lý chi nhánh <span
                    aria-hidden="true">↗</span></span>
        </a>
    </div>
    <section class="dashboard-card mt-6"><span class="eyebrow">THÔNG TIN CỦA BẠN</span>
        <dl class="profile-facts">
            <div>
                <dt>Email</dt>
                <dd>{{ auth()->user()->email }}</dd>
            </div>
            <div>
                <dt>Số điện thoại</dt>
                <dd>{{ auth()->user()->phone ?: 'Chưa cập nhật' }}</dd>
            </div>
        </dl>
        @can('view-platform')
            <a href="{{ route('admin.dashboard') }}" class="btn">Quản trị nền tảng ↗</a>
        @endcan
    </section>
@endsection
