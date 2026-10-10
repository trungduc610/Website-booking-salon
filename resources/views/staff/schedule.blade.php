@extends('layouts.app')
@section('title', 'Giờ mở cửa')
@section('content')
<section class="dashboard-card"><a href="{{ route('staff.index',$branch) }}">← Nhân viên</a><h1>Giờ mở cửa · {{ $branch->name }}</h1><p>Múi giờ: {{ $branch->timezone }}</p><x-errors />
<x-schedule-conflicts :branch="$branch" />
<form method="post" action="{{ route('schedule.update',$branch) }}">@csrf @method('PUT')
<x-weekly-hours :rows="$hours" :branch-hours="true" />
<div class="row g-3">
@foreach(['lead_time_minutes'=>['Đặt trước ít nhất (phút)',60], 'booking_horizon_days'=>['Nhận lịch tối đa (ngày)',90], 'default_buffer_minutes'=>['Nghỉ giữa hai lịch (phút)',0], 'cancellation_hours'=>['Hủy trước ít nhất (giờ)',24], 'reschedule_hours'=>['Khách tự đổi lịch trước ít nhất (giờ)',12]] as $field => [$label,$default])
<div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input type="number" min="0" class="form-control" name="{{ $field }}" id="{{ $field }}" required @if($field === 'reschedule_hours') max="168" step="1" aria-describedby="reschedule-hours-help" @endif value="{{ old($field,$policy?->$field ?? $default) }}">
@if($field === 'reschedule_hours')<p id="reschedule-hours-help" class="field-hint">Thời hạn khách tự đổi lịch trước giờ hẹn. Ví dụ đặt 12: khách phải đổi lịch ít nhất 12 giờ trước giờ hẹn. Đặt 0 để khách có thể tự đổi lịch đến giờ bắt đầu hẹn.</p>@endif
</div>@endforeach
</div><button class="btn btn-primary mt-3">Lưu giờ mở cửa</button></form>
<hr class="my-4"><h2 class="h4">Ngày đóng cửa đặc biệt</h2>
<form method="post" action="{{ route('schedule.holiday',$branch) }}">@csrf<div class="row g-3"><div class="col-md-4"><label for="date">Ngày</label><input id="date" class="form-control" name="date" type="date" required></div><div class="col-md-8"><label for="name">Lý do</label><input id="name" class="form-control" name="name" maxlength="200" required></div></div><button class="btn mt-3">Thêm ngày nghỉ</button></form>
@foreach($holidays as $holiday)<div class="border rounded p-3 mt-3">{{ $holiday->date }} · {{ $holiday->name }}@if($holiday->is_closed)<form method="post" action="{{ route('schedule.reopen',[$branch,$holiday->id]) }}">@csrf @method('PATCH')<button class="btn btn-sm mt-2">Mở lại</button></form>@else<p>Đã mở lại</p>@endif</div>@endforeach
{{ $holidays->links() }}</section>
@endsection
