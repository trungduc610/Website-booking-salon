@extends('layouts.app')
@section('title', 'Hồ sơ và lịch làm')
@section('content')
<section class="dashboard-card">
<a href="{{ route('staff.index', $branch) }}">← Nhân viên</a><h1>{{ $staff->exists ? $staff->full_name : 'Thêm nhân viên' }}</h1><x-errors />
<x-schedule-conflicts :branch="$branch" />
<form method="post" action="{{ $staff->exists ? route('staff.update', [$branch, $staff]) : route('staff.store', $branch) }}">@csrf @if($staff->exists) @method('PUT') @endif
<div class="row g-3">
@foreach(['full_name' => 'Họ tên', 'position' => 'Chức danh', 'employee_code' => 'Mã nhân viên'] as $field => $label)
<div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $staff->$field) }}" @required($field === 'full_name')></div>@endforeach
<div class="col-md-6"><label for="status" class="form-label">Trạng thái</label><select name="status" id="status" class="form-control">@foreach(['ACTIVE'=>'Đang làm việc','INACTIVE'=>'Ngừng hoạt động','ON_LEAVE'=>'Đang nghỉ','LOCKED'=>'Đã khóa'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$staff->status)===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="is_bookable" class="form-label">Cho phép nhận lịch</label><select id="is_bookable" name="is_bookable" class="form-control"><option value="0" @selected(!old('is_bookable',$staff->is_bookable))>Không</option><option value="1" @selected(old('is_bookable',$staff->is_bookable))>Có, khi còn ca làm phù hợp</option></select></div>
<div class="col-12"><label for="bio" class="form-label">Giới thiệu</label><textarea class="form-control" id="bio" name="bio" maxlength="2000">{{ old('bio',$staff->bio) }}</textarea></div>
<fieldset class="col-12"><legend class="h6">Dịch vụ được thực hiện</legend>@forelse($services as $service)<label class="d-block"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids',$staff->services->modelKeys())))> {{ $service->name }}</label>@empty<p>Hãy thêm dịch vụ cho chi nhánh trước.</p>@endforelse</fieldset>
</div><button class="btn btn-primary mt-3">Lưu hồ sơ</button></form>
@if($staff->exists)
<hr class="my-4"><h2 class="h4">Lịch làm theo tuần</h2><form method="post" action="{{ route('staff.hours', [$branch,$staff]) }}">@csrf @method('PUT')<x-weekly-hours :rows="$staff->hours" /><button class="btn btn-primary">Lưu lịch làm</button></form>
<hr class="my-4"><h2 class="h4">Thêm khoảng nghỉ</h2><form method="post" action="{{ route('staff.leave', [$branch,$staff]) }}">@csrf
<div class="row g-3"><div class="col-md-6"><label for="start_at">Từ</label><input class="form-control" type="datetime-local" id="start_at" name="start_at" required value="{{ old('start_at') }}"></div><div class="col-md-6"><label for="end_at">Đến</label><input class="form-control" type="datetime-local" id="end_at" name="end_at" required value="{{ old('end_at') }}"></div><div class="col-12"><label for="reason">Lý do (không bắt buộc)</label><input class="form-control" id="reason" name="reason" maxlength="500" value="{{ old('reason') }}"></div></div><button class="btn mt-3">Lưu khoảng nghỉ</button></form>
<div class="mt-4">@foreach($leaves as $leave)<div class="border rounded p-3 mb-2"><p>{{ $leave->start_at }} → {{ $leave->end_at }} · {{ $leave->status === 'CANCELLED' ? 'Đã hủy' : 'Đã ghi nhận' }}</p><p>{{ $leave->reason }}</p>@if($leave->status !== 'CANCELLED')<form method="post" action="{{ route('staff.leave.cancel', [$branch,$staff,$leave->id]) }}">@csrf @method('PATCH')<button class="btn">Hủy khoảng nghỉ</button></form>@endif</div>@endforeach{{ $leaves->links() }}</div>
@endif
</section>
@endsection
