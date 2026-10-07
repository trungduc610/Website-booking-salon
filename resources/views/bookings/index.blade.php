@extends('layouts.app')
@section('title','Lịch hẹn')
@section('content')
<section class="dashboard-card"><h1>{{ $branch ? 'Lịch hẹn · '.$branch->name : 'Lịch hẹn của bạn' }}</h1><x-errors />
<div class="table-responsive"><table class="table"><thead><tr><th>Mã lịch</th><th>Thời gian</th><th>Trạng thái</th><th>Tổng tiền</th><th>Thao tác</th></tr></thead><tbody>
@forelse($bookings as $booking)<tr><td>{{ $booking->booking_code }}</td><td>{{ $booking->appointment_date }}<br>{{ substr($booking->appointment_start_time,0,5) }}–{{ substr($booking->appointment_end_time,0,5) }}</td><td>{{ __('booking.statuses.'.$booking->status) }}</td><td>{{ number_format((float)$booking->final_amount,2,',','.') }} ₫</td><td>
@if($branch)
<a href="{{ route('salon.payments.show',[$branch,$booking]) }}">Thanh toán và hoàn tiền</a>
<form method="post" action="{{ route('salon.bookings.status',[$branch,$booking]) }}">@csrf @method('PATCH')<label class="visually-hidden" for="status-{{ $booking->id }}">Chuyển trạng thái</label><select id="status-{{ $booking->id }}" name="status" class="form-control">@foreach(['CONFIRMED','REJECTED','CHECKED_IN','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW'] as $status)<option value="{{ $status }}">{{ __('booking.statuses.'.$status) }}</option>@endforeach</select><button class="btn btn-sm mt-2">Cập nhật</button></form>
@else<a href="{{ route('bookings.show',$booking) }}">Chi tiết</a>@endif
</td></tr>@empty<tr><td colspan="5">Chưa có lịch hẹn.</td></tr>@endforelse
</tbody></table></div>{{ $bookings->links() }}</section>
@endsection
