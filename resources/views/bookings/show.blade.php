@extends('layouts.app')
@section('title','Chi tiết lịch hẹn')
@section('content')
<section class="dashboard-card"><a href="{{ route('bookings.index') }}">← Lịch hẹn của bạn</a><h1>{{ $booking->booking_code }}</h1><x-errors />
<p>{{ $booking->branch->name }} · {{ $booking->appointment_date }} · {{ substr($booking->appointment_start_time,0,5) }}–{{ substr($booking->appointment_end_time,0,5) }}</p>
<p>Trạng thái: <strong>{{ __('booking.statuses.'.$booking->status) }}</strong></p>
@if($booking->status === 'PENDING')<p>Giữ chỗ đến {{ $booking->pending_expires_at }} ({{ config('app.timezone') }}).</p>@endif
<ul>@foreach($booking->items as $item)<li>{{ $item->service_name_snapshot }} · {{ number_format((float)$item->price_at_booking,2,',','.') }} ₫</li>@endforeach</ul>
<p>Giá dịch vụ: {{ number_format((float)$booking->total_amount,2,',','.') }} ₫ · Giảm ưu đãi: {{ number_format((float)$booking->voucher_discount_amount,2,',','.') }} ₫</p><p>Tổng tiền: <strong>{{ number_format((float)$booking->final_amount,2,',','.') }} ₫</strong></p><p>{{ $booking->note }}</p>
@if(in_array($booking->status,['PENDING','CONFIRMED'],true))<form method="post" action="{{ route('bookings.cancel',$booking) }}">@csrf @method('PATCH')<button class="btn">Hủy lịch hẹn</button></form><small>Việc hủy tuân theo thời hạn của chi nhánh.</small>@endif
<p class="mt-3"><a class="btn" href="{{ route('payments.show',$booking) }}">Thanh toán và hoàn tiền</a></p></section>
@endsection
