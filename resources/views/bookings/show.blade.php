@extends('layouts.app')
@section('title', 'Chi tiết lịch hẹn')
@section('content')
    <a class="btn mb-4" href="{{ route('bookings.index') }}">
        ← Lịch hẹn của bạn</a><x-errors />
    <div class="booking-layout">
        <section class="dashboard-card"><span class="eyebrow">A MOMENT RESERVED FOR YOU</span><span
                class="status-badge status-{{ $booking->status }}">{{ __('booking.statuses.' . $booking->status) }}</span>
            <h1>{{ $booking->branch->name }}</h1>
            <p class="ticket-code">{{ $booking->booking_code }}</p>
            <dl class="profile-facts">
                <div>
                    <dt>Ngày hẹn</dt>
                    <dd>{{ \Carbon\CarbonImmutable::parse($booking->appointment_date)->format('d / m / Y') }}</dd>
                </div>
                <div>
                    <dt>Thời gian · {{ $booking->branch->timezone }}</dt>
                    <dd>{{ substr($booking->appointment_start_time, 0, 5) }}–{{ substr($booking->appointment_end_time, 0, 5) }}
                    </dd>
                </div>
            </dl>
            @if ($booking->status === 'PENDING' && $booking->pending_expires_at)
                <div class="notice"><strong role="timer" aria-live="off"
                        data-hold-until="{{ \Carbon\CarbonImmutable::parse($booking->pending_expires_at, config('app.timezone'))->toIso8601String() }}">Giữ
                        chỗ đến {{ $booking->pending_expires_at }}</strong>
                    <p class="mb-0">Salon cần xác nhận trước khi hết thời hạn giữ chỗ.</p>
                </div>
            @endif
            <p>{{ $booking->branch->address_line }}</p>
            @if ($booking->branch->address_line)
                <a class="btn" target="_blank" rel="noopener noreferrer"
                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode($booking->branch->address_line) }}">Chỉ
                    đường ↗</a>
            @endif
            @if ($booking->note)
                <div class="customer-detail"><strong>Lời nhắn cho salon</strong>
                    <p class="mb-0">{{ $booking->note }}</p>
                </div>
            @endif
            @if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true) && $booking->items->isNotEmpty())
                <details class="mt-4">
                    <summary class="btn">Đổi thời gian hẹn</summary>
                    <p class="field-hint mt-3">Giữ nguyên dịch vụ, chuyên viên, giá và ưu đãi. Việc đổi lịch tuân theo thời
                        hạn của salon và chỉ hoàn tất khi khung giờ mới còn trống.</p>
                    <form method="post" action="{{ route('bookings.reschedule', $booking) }}" class="row g-3">@csrf
                        @method('PATCH')<input type="hidden" name="original_start"
                            value="{{ $booking->appointment_date . ' ' . $booking->appointment_start_time }}"><input
                            type="hidden" name="original_staff_id" value="{{ $booking->items->first()->staff_id }}">
                        <div class="col-md-6"><label for="reschedule-date" class="form-label">Ngày mới</label><input
                                class="form-control" type="date" id="reschedule-date" name="date"
                                value="{{ old('date', $booking->appointment_date) }}" required></div>
                        <div class="col-md-6"><label for="reschedule-time" class="form-label">Giờ mới</label><input
                                class="form-control" type="time" id="reschedule-time" name="time"
                                value="{{ old('time', substr($booking->appointment_start_time, 0, 5)) }}" required></div>
                        <div><button class="btn btn-primary">Xác nhận đổi lịch</button></div>
                    </form>
                </details>
            @endif
            @if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true))
                <details class="mt-4">
                    <summary class="btn">Quản lý / hủy cuộc hẹn</summary>
                    <p class="mt-3 muted">Việc hủy tuân theo thời hạn của chi nhánh. Hủy lịch không tự động hoàn tiền.</p>
                    <form method="post" action="{{ route('bookings.cancel', $booking) }}"
                        data-confirm="Bạn chắc chắn muốn hủy cuộc hẹn này?">@csrf @method('PATCH')<button
                            class="btn">Hủy lịch hẹn</button></form>
                </details>
            @endif
        </section>
        <aside class="booking-summary"><span class="eyebrow">YOUR BEAUTY RITUAL</span>
            <h2>Chi tiết dịch vụ</h2>
            <ul>
                @foreach ($booking->items as $item)
                    <li>{{ $item->service_name_snapshot }}<br><span
                            class="muted">{{ number_format((float) $item->price_at_booking, 2, ',', '.') }} ₫</span></li>
                @endforeach
            </ul>
            <dl>
                <div>
                    <dt>Giá dịch vụ</dt>
                    <dd>{{ number_format((float) $booking->total_amount, 2, ',', '.') }} ₫</dd>
                </div>
                <div>
                    <dt>Giảm ưu đãi</dt>
                    <dd>−{{ number_format((float) $booking->voucher_discount_amount, 2, ',', '.') }} ₫</dd>
                </div>
                <div class="summary-total">
                    <dt>Tổng tiền</dt>
                    <dd>{{ number_format((float) $booking->final_amount, 2, ',', '.') }} ₫</dd>
                </div>
            </dl><a class="btn btn-primary w-full mt-4" href="{{ route('payments.show', $booking) }}">Thanh toán và hoàn tiền
                ↗</a>
        </aside>
    </div>
@endsection
