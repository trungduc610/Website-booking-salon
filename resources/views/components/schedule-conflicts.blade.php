@props(['branch'])
@if(session('schedule_conflicts'))
<div class="alert alert-warning" role="region" aria-label="Lịch hẹn xung đột">
    <p class="mb-2">Mở lịch xung đột để xử lý bằng chức năng Đổi lịch khi còn được phép:</p>
    <ul class="mb-0">
        @foreach(session('schedule_conflicts') as $conflict)
            @if((int) $conflict['branch_id'] === (int) $branch->id)
            <li><a href="{{ route('salon.bookings', [$branch, 'date' => $conflict['date'], 'booking_id' => $conflict['id']]).'#booking-'.$conflict['id'] }}">{{ $conflict['code'] }} · {{ $conflict['date'] }} {{ $conflict['start'] }}–{{ $conflict['end'] }} · Mở lịch</a></li>
            @endif
        @endforeach
    </ul>
</div>
@endif
