@extends('layouts.app')
@section('title', 'Lịch hẹn')
@section('content')
    <div class="section-heading">
        <div><span class="eyebrow">{{ $branch ? 'SALON OPERATIONS' : 'YOUR UPCOMING MOMENTS' }}</span>
            <h1>{{ $branch ? 'Lịch hẹn · ' . $branch->name : 'Thời gian dành cho bạn.' }}</h1>
        </div>
        @unless ($branch)
            <a class="btn btn-primary" href="{{ route('salons.index') }}">Đặt lịch mới ↗</a>
        @endunless
    </div>
    <x-errors />
    @if ($branch)
        <div class="metric-grid">
            <dl class="metric-card">
                <dt>Lịch hẹn hôm nay</dt>
                <dd>{{ $metrics['today'] }}</dd><dd class="metric-caption"><small>{{ $metrics['date'] }} · {{ $branch->timezone }}</small><x-sparkline
                    :values="$metrics['trend']" label="Số lịch hẹn trong 7 ngày gần nhất, từ cũ đến mới" /></dd></dl>
            <dl class="metric-card">
                <dt>Đang chờ xác nhận</dt>
                <dd>{{ $metrics['pending'] }}</dd><dd class="metric-caption"><small>Lịch còn thời hạn giữ chỗ</small></dd></dl>
            <dl class="metric-card">
                <dt>Hoàn tất hôm nay</dt>
                <dd>{{ $metrics['completed'] }}</dd><dd class="metric-caption"><small>Cuộc hẹn đã hoàn thành</small></dd></dl>
            <dl class="metric-card">
                <dt>Đã thu hôm nay</dt>
                <dd>{{ number_format((float) $metrics['received'], 0, ',', '.') }} ₫</dd><dd class="metric-caption"><small>Tiền đã ghi nhận, trước hoàn
                    tiền</small></dd></dl>
            <dl class="metric-card">
                <dt>Tỷ lệ không đến hôm nay</dt>
                <dd>{{ $metrics['noShowRate'] === null ? '—' : $metrics['noShowRate'] . '%' }}</dd><dd class="metric-caption"><small>Trong các lịch hoàn
                    tất / không đến</small></dd></dl>
        </div>
        @include('components.schedule-timeline')<section class="dashboard-card">
            <div class="section-heading">
                <div><span class="eyebrow">APPOINTMENT DESK</span>
                    <h2>Quản lý cuộc hẹn</h2>
                </div>
            </div>
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-4"><label for="filter-date" class="form-label">Ngày hẹn</label><input
                        class="form-control" id="filter-date" name="date" type="date" value="{{ request('date') }}">
                </div>
                <div class="col-md-4"><label for="filter-status" class="form-label">Trạng thái</label><select
                        class="form-control" id="filter-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach (['PENDING', 'CONFIRMED', 'CHECKED_IN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW', 'REJECTED', 'EXPIRED'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ __('booking.statuses.' . $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 align-items-end"><button class="btn btn-primary">Lọc lịch hẹn</button><a
                        class="btn" href="{{ route('salon.bookings', $branch) }}">Đặt lại</a></div>
            </form>
            <div class="table-responsive">
                <table class="table">
                    <caption class="visually-hidden">Lịch hẹn của {{ $branch->name }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Cuộc hẹn</th>
                            <th scope="col">Thời gian</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Tổng tiền</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            <tr id="booking-{{ $booking->id }}">
                                <td><span
                                        class="ticket-code">{{ $booking->booking_code }}</span><br><small>{{ $booking->items->pluck('service_name_snapshot')->join(', ') }}</small>
                                </td>
                                <td>{{ $booking->appointment_date }}<br>{{ substr($booking->appointment_start_time, 0, 5) }}–{{ substr($booking->appointment_end_time, 0, 5) }}
                                </td>
                                <td><span
                                        class="status-badge status-{{ $booking->status }}">{{ __('booking.statuses.' . $booking->status) }}</span>
                                </td>
                                <td>{{ number_format((float) $booking->final_amount, 2, ',', '.') }} ₫</td>
                                <td>
                                    <a href="{{ route('salon.payments.show', [$branch, $booking]) }}">Thanh toán và hoàn tiền
                                        ↗</a>
                                    @if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true) && $booking->items->isNotEmpty())
                                        <button type="button" class="btn btn-sm mb-2" data-reschedule-open
                                            data-reschedule-url="{{ route('salon.bookings.reschedule', [$branch, $booking]) }}"
                                            data-original-start="{{ $booking->appointment_date . ' ' . $booking->appointment_start_time }}"
                                            data-original-staff="{{ $booking->items->first()->staff_id }}"
                                            data-booking-code="{{ $booking->booking_code }}">Đổi lịch</button>
                                    @endif
                                    @php($transitions = ['PENDING' => ['CONFIRMED', 'REJECTED', 'CANCELLED'], 'CONFIRMED' => ['CHECKED_IN', 'CANCELLED', 'NO_SHOW'], 'CHECKED_IN' => ['IN_PROGRESS', 'CANCELLED', 'NO_SHOW'], 'IN_PROGRESS' => ['COMPLETED', 'CANCELLED']])
                                    @if (isset($transitions[$booking->status]))
                                        <form method="post"
                                            action="{{ route('salon.bookings.status', [$branch, $booking]) }}">@csrf
                                            @method('PATCH')<label class="visually-hidden"
                                                for="status-{{ $booking->id }}">Chuyển trạng thái
                                                {{ $booking->booking_code }}</label><select
                                                id="status-{{ $booking->id }}" name="status" class="form-control">
                                                @foreach ($transitions[$booking->status] as $status)
                                                    <option value="{{ $status }}">
                                                        {{ __('booking.statuses.' . $status) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm mt-2">Cập nhật</button>
                                        </form>
                                    @endif
                                </td>
                        </tr>@empty<tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <h3>Một khoảng trống trong lịch</h3>
                                        <p>Chưa có lịch hẹn phù hợp với bộ lọc.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>{{ $bookings->links() }}
        </section>
        <dialog class="reschedule-dialog" data-reschedule-dialog aria-labelledby="reschedule-title">
            <form method="post" data-reschedule-form>@csrf @method('PATCH')<span class="eyebrow">A NEW MOMENT</span>
                <h2 id="reschedule-title">Đổi lịch hẹn</h2>
                <p data-reschedule-code class="ticket-code"></p><input type="hidden" name="original_start"><input
                    type="hidden" name="original_staff_id"><label for="move-date" class="form-label">Ngày hẹn
                    mới</label><input id="move-date" class="form-control mb-3" name="date" type="date" required><label
                    for="move-time" class="form-label">Giờ bắt đầu mới</label><input id="move-time"
                    class="form-control mb-3" name="time" type="time" required><label for="move-staff"
                    class="form-label">Chuyên viên</label><select id="move-staff" name="staff_id" class="form-control">
                    @foreach ($scheduleStaff as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <p class="field-hint mt-3">Hệ thống kiểm tra xung đột trước khi lưu. Giá, dịch vụ, ưu đãi và thời hạn giữ
                    chỗ không thay đổi.</p>
                <div class="flex flex-wrap gap-3 mt-4"><button class="btn" type="button" data-reschedule-close>Quay
                        lại</button><button class="btn btn-primary">Xác nhận đổi lịch</button></div>
            </form>
        </dialog>
    @else
        <div class="ticket-list">
            @forelse($bookings as $booking)
                <article class="appointment-ticket">
                    <div class="ticket-date">
                        <strong>{{ \Carbon\CarbonImmutable::parse($booking->appointment_date)->format('d') }}</strong>{{ \Carbon\CarbonImmutable::parse($booking->appointment_date)->format('m / Y') }}
                    </div>
                    <div class="ticket-info"><span
                            class="status-badge status-{{ $booking->status }}">{{ __('booking.statuses.' . $booking->status) }}</span>
                        <h2>{{ $booking->branch->name }}</h2>
                        <p>{{ substr($booking->appointment_start_time, 0, 5) }}–{{ substr($booking->appointment_end_time, 0, 5) }}
                            · {{ $booking->branch->timezone }}</p>
                        <p>{{ number_format((float) $booking->final_amount, 2, ',', '.') }} ₫</p><span
                            class="ticket-code">{{ $booking->booking_code }}</span>
                    </div><a class="btn" href="{{ route('bookings.show', $booking) }}">Chi tiết cuộc hẹn ↗</a>
            </article>@empty<div class="empty-state"><span aria-hidden="true">✧</span>
                    <h2>Cuộc hẹn đầu tiên đang chờ.</h2>
                    <p>Khám phá salon và dành một chút thời gian cho bản thân.</p><a class="btn btn-primary"
                        href="{{ route('salons.index') }}">Khám phá salon ↗</a>
                </div>
            @endforelse
        </div>{{ $bookings->links() }}
    @endif
@endsection
