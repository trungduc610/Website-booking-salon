<section class="dashboard-card mb-4">
    <div class="section-heading">
        <div><span class="eyebrow">THE DAILY RHYTHM</span>
            <h2>Lịch chuyên viên · {{ \Carbon\CarbonImmutable::parse($scheduleDate)->format('d/m/Y') }}</h2>
        </div><span class="muted">{{ $branch->timezone }}</span>
    </div>
    <p class="field-hint">Lịch đang có hiệu lực. Vùng gạch chéo biểu thị {{ $bufferMinutes }} phút đệm. Kéo lịch sang
        khung giờ mới, hoặc dùng nút Đổi lịch trong danh sách. Xác nhận trước khi lưu.</p>
    <div class="schedule-scroll" tabindex="0" role="region" aria-label="Lịch chuyên viên, cuộn ngang để xem đủ 24 giờ">
        <div class="schedule-grid">
            <div class="schedule-hours"><span>Chuyên viên</span>
                <div>
                    @for ($hour = 0; $hour < 24; $hour += 2)
                        <span>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                    @endfor
                </div>
            </div>
            @forelse($scheduleStaff as $member)
                <div class="schedule-row">
                    <div class="schedule-name"><span class="staff-avatar"
                            aria-hidden="true">{{ mb_substr($member->full_name, 0, 1) }}</span><strong>{{ $member->full_name }}</strong>
                    </div>
                    <div class="schedule-track" data-drop-date="{{ $scheduleDate }}"
                        data-drop-staff="{{ $member->id }}">
                        @foreach ($schedule as $appointment)
                            @if ($appointment->items->contains('staff_id', $member->id))
                                @php
                                    $start = \Carbon\CarbonImmutable::parse($appointment->appointment_start_time);
                                    $end = \Carbon\CarbonImmutable::parse($appointment->appointment_end_time);
                                    $left = (($start->hour * 60 + $start->minute) / 1440) * 100;
                                    $width = max(
                                        0.5,
                                        (($end->hour * 60 + $end->minute - ($start->hour * 60 + $start->minute)) /
                                            1440) *
                                            100,
                                    );
                                    $bufferWidth = min(($bufferMinutes / 1440) * 100, 100 - $left - $width);
                                @endphp
                                <a @if (in_array($appointment->status, ['PENDING', 'CONFIRMED'], true)) draggable="true" data-reschedule-drag data-reschedule-url="{{ route('salon.bookings.reschedule', [$branch, $appointment]) }}" data-original-start="{{ $appointment->appointment_date . ' ' . $appointment->appointment_start_time }}" data-original-staff="{{ $member->id }}" data-booking-code="{{ $appointment->booking_code }}" @endif
                                    class="schedule-event status-{{ $appointment->status }}"
                                    data-timeline-left="{{ $left }}" data-timeline-width="{{ $width }}"
                                    href="{{ route('salon.payments.show', [$branch, $appointment]) }}"
                                    aria-label="{{ $member->full_name }}, {{ $start->format('H:i') }} đến {{ $end->format('H:i') }}, {{ $appointment->items->pluck('service_name_snapshot')->join(', ') }}, {{ __('booking.statuses.' . $appointment->status) }}"
                                    title="{{ $appointment->items->pluck('service_name_snapshot')->join(', ') }} · {{ $start->format('H:i') }}–{{ $end->format('H:i') }}"><strong>{{ $start->format('H:i') }}</strong><span>{{ $appointment->items->pluck('service_name_snapshot')->join(', ') }}</span></a>
                                @if ($bufferMinutes > 0)
                                    <span class="schedule-buffer"
                                        data-timeline-left="{{ $left + $width }}" data-timeline-width="{{ $bufferWidth }}"
                                        aria-hidden="true"></span>
                                @endif
                            @endif
                        @endforeach
                    </div>
            </div>@empty<p class="empty-state">Chưa có chuyên viên tại chi nhánh này.</p>
            @endforelse
        </div>
    </div>
    <div class="flex flex-wrap gap-3 mt-4"><span class="status-badge status-PENDING">Chờ xác nhận</span><span
            class="status-badge status-CONFIRMED">Đã xác nhận / đang phục vụ</span></div>
</section>

