@extends('layouts.app')
@section('title','Đặt lịch')
@section('content')
<section class="dashboard-card"><a href="{{ route('salons.index') }}">← Danh sách salon</a><h1>Đặt lịch · {{ $branch->name }}</h1><p>Giờ địa phương: {{ $branch->timezone }}. Lịch chỉ được giữ sau khi gửi thành công.</p><x-errors />
<form method="post" action="{{ route('bookings.store',$branch) }}" data-submit-form data-availability-url="{{ route('bookings.availability',$branch) }}">@csrf
<input type="hidden" name="request_token" value="{{ old('request_token', (string) Illuminate\Support\Str::uuid()) }}">
<fieldset><legend class="h5">Dịch vụ</legend>@forelse($services as $service)<label class="d-block border rounded p-3 mb-2"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id,old('service_ids',[])))> {{ $service->name }} · {{ number_format((float)$service->price,2,',','.') }} ₫ · {{ $service->duration_minutes }} phút</label>@empty<p>Chưa có dịch vụ nhận lịch.</p>@endforelse</fieldset>
<div class="row g-3 mt-2"><div class="col-md-6"><label for="date" class="form-label">Ngày hẹn</label><input type="date" id="date" name="date" class="form-control" value="{{ old('date') }}" required></div><div class="col-md-6"><label for="time" class="form-label">Giờ bắt đầu</label><input type="time" id="time" name="time" class="form-control" value="{{ old('time') }}" required></div>
<div class="col-12"><label for="staff_id" class="form-label">Nhân viên</label><select name="staff_id" id="staff_id" class="form-control"><option value="">Bất kỳ nhân viên phù hợp</option>@foreach($staffList as $staff)<option value="{{ $staff->id }}" @selected(old('staff_id')==$staff->id)>{{ $staff->full_name }}</option>@endforeach</select></div>
<div class="col-12"><label for="note" class="form-label">Ghi chú</label><textarea name="note" id="note" class="form-control" maxlength="1000">{{ old('note') }}</textarea></div></div>
<div class="mt-3"><label for="voucher_code">Mã ưu đãi (không bắt buộc)</label><input class="form-control" id="voucher_code" name="voucher_code" maxlength="50" value="{{ old('voucher_code') }}"><small>Mã được kiểm tra và trừ tiền khi gửi đặt lịch thành công.</small></div><button class="btn mt-3" type="button" data-check-availability>Kiểm tra khung giờ</button><p role="status" data-availability-status></p>
<button class="btn btn-primary" type="submit" data-submit-button @disabled($services->isEmpty())><span data-button-label>Gửi yêu cầu đặt lịch</span><span class="button-spinner" aria-hidden="true" hidden></span></button><p class="form-status" role="status" data-submit-status></p>
</form></section>
@endsection
