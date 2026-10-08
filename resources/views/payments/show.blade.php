@extends('layouts.app')
@section('title','Thanh toán và hoàn tiền')
@section('content')
<section class="dashboard-card">
<a href="{{ $salon ? route('salon.bookings',$booking->branch) : route('bookings.show',$booking) }}">← Lịch hẹn</a>
<h1>Thanh toán · {{ $booking->booking_code }}</h1>
<x-errors />
<p>{{ $booking->branch->name }} · {{ __('booking.statuses.'.$booking->status) }}</p>
<p>Thành tiền sau ưu đãi: <strong>{{ number_format((float)$booking->final_amount,2,',','.') }} ₫</strong></p>
<dl class="row">
@foreach(['received'=>'Đã thu','remaining'=>'Còn phải thu','refunded'=>'Đã hoàn','net'=>'Thực giữ sau hoàn'] as $key=>$label)
<dt class="col-sm-6">{{ $label }}</dt><dd class="col-sm-6">{{ number_format($totals[$key]/100,2,',','.') }} ₫</dd>
@endforeach
</dl>
<p>Hủy lịch không tự hoàn tiền. Yêu cầu hoàn chỉ hoàn tất khi salon xác nhận đã trả tiền. Khoản đã hoàn không làm phát sinh thu lại.</p>
@if($salon && $totals['remaining'] > 0 && in_array($booking->status,['CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED'],true))
<h2>Ghi nhận tiền thực nhận</h2>
<form method="post" action="{{ route('salon.payments.collect',[$booking->branch,$booking]) }}" class="mb-4">
@csrf
<input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
<label for="collect-amount">Số tiền (₫)</label><input id="collect-amount" class="form-control" name="amount" type="number" min="0.01" step="0.01" max="{{ \App\Services\VoucherDiscount::decimal($totals['remaining']) }}" value="{{ old('amount',\App\Services\VoucherDiscount::decimal($totals['remaining'])) }}" required>
<label for="collect-method">Hình thức</label><select id="collect-method" class="form-control" name="method"><option value="CASH" @selected(old('method') === 'CASH')>Tiền mặt</option><option value="BANK_TRANSFER" @selected(old('method') === 'BANK_TRANSFER')>Chuyển khoản</option></select>
<label for="collect-ref">Mã giao dịch (bắt buộc với chuyển khoản hoặc MoMo)</label><input id="collect-ref" class="form-control" name="transaction_ref" maxlength="200" value="{{ old('transaction_ref') }}">
<p class="mt-2">Chỉ ghi nhận sau khi kiểm tra tiền đã thực nhận.</p><button class="btn">Xác nhận đã nhận tiền</button>
</form>
@endif
@if($momoEnabled && !$salon && $totals['remaining'] > 0 && in_array($booking->status,['CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED'],true))
<section class="momo-panel mb-4"><span class="eyebrow">MOMO CHECKOUT</span><h2>Thanh toán qua MoMo</h2><p>Thanh toán số dư {{ number_format($totals['remaining']/100,2,',','.') }} ₫. Bạn sẽ chuyển đến MoMo để xác nhận; GlowBook chỉ ghi nhận khi nhận được kết quả xác minh.</p>@if(config('momo.environment') === 'sandbox')<p class="notice">Môi trường thử nghiệm MoMo — không phải thanh toán thực.</p>@endif<form method="post" action="{{ route('momo.checkout',$booking) }}" data-submit-form>@csrf<button class="btn btn-momo" data-submit-button><span>Tiếp tục với MoMo ↗</span><span class="button-spinner" aria-hidden="true" hidden></span></button><p role="status" data-submit-status></p></form></section>
@endif<h2>Lịch sử thu và hoàn tiền</h2>
@forelse($payments as $payment)
<article class="border rounded p-3 mb-3">
<h3>Giao dịch #{{ $payment->id }} · {{ number_format((float)$payment->amount,2,',','.') }} ₫</h3>
<p>{{ __('payment.methods.'.$payment->method) }} · {{ __('payment.statuses.'.$payment->status) }} · {{ $payment->paid_at }} ({{ config('app.timezone') }})</p>
@if($momoEnabled && $payment->method === 'MOMO' && $payment->status === 'PENDING')<div class="notice"><p>Giao dịch MoMo đang chờ xác minh. Chưa thu thêm tiền trực tiếp cho đến khi đối soát xong.</p><form method="post" action="{{ route('momo.reconcile',[$booking,$payment]) }}" data-submit-form>@csrf<button class="btn" data-submit-button>Cập nhật trạng thái MoMo<span class="button-spinner" aria-hidden="true" hidden></span></button><p data-submit-status role="status"></p></form></div>@endif
@if($payment->transaction_ref)<p>Mã giao dịch: {{ $payment->transaction_ref }}</p>@endif
@php($available = \App\Services\VoucherDiscount::cents($payment->amount) - $payment->refunds->whereIn('status',\App\Services\PaymentManager::RESERVED)->sum(fn($r)=>\App\Services\VoucherDiscount::cents($r->amount)))
@if($available > 0 && in_array($payment->status,['PAID','PARTIALLY_REFUNDED'],true))
<details class="mb-3"><summary>Yêu cầu hoàn tiền (tối đa {{ number_format($available/100,2,',','.') }} ₫)</summary>
<form method="post" action="{{ $salon ? route('salon.refunds.request',[$booking->branch,$booking,$payment]) : route('refunds.request',[$booking,$payment]) }}">
@csrf<input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label for="refund-amount-{{ $payment->id }}">Số tiền hoàn (₫)</label><input id="refund-amount-{{ $payment->id }}" class="form-control" name="amount" type="number" min="0.01" step="0.01" max="{{ \App\Services\VoucherDiscount::decimal($available) }}" required>
<label for="refund-reason-{{ $payment->id }}">Lý do</label><textarea id="refund-reason-{{ $payment->id }}" class="form-control" name="reason" maxlength="2000" required></textarea><button class="btn mt-2">Gửi yêu cầu hoàn tiền</button>
</form></details>
@endif
@foreach($payment->refunds as $refund)
<div class="border-top pt-2 mt-2">
<p><strong>Yêu cầu #{{ $refund->id }}: {{ number_format((float)$refund->amount,2,',','.') }} ₫ · {{ __('payment.statuses.'.$refund->status) }}</strong></p>
<p>{{ $refund->reason }}</p>
@if($refund->review_note)<p>Ghi chú xét duyệt: {{ $refund->review_note }}</p>@endif
@if($refund->processed_at)<p>Đã trả tiền: {{ $refund->processed_at }} · Mã giao dịch: {{ $refund->transaction_ref ?? 'Tiền mặt' }}</p>@endif
@if($salon && auth()->user()->can('update',$booking->branch) && in_array($refund->status,['PENDING','APPROVED'],true))
<form method="post" action="{{ route('salon.refunds.review',[$booking->branch,$booking,$refund]) }}">
@csrf @method('PATCH')
<label for="review-note-{{ $refund->id }}">Ghi chú (bắt buộc khi từ chối)</label><textarea id="review-note-{{ $refund->id }}" class="form-control" name="review_note" maxlength="2000"></textarea>
@if($refund->status === 'APPROVED')
<label for="review-ref-{{ $refund->id }}">Mã giao dịch hoàn (bắt buộc với chuyển khoản hoặc MoMo)</label><input id="review-ref-{{ $refund->id }}" class="form-control" name="transaction_ref" maxlength="200">
<p>Chỉ xác nhận sau khi đã trả tiền thành công. Với MoMo, thực hiện hoàn trong cổng merchant và nhập mã giao dịch hoàn tiền.</p><button class="btn mt-2" name="status" value="REFUNDED">Xác nhận đã trả tiền</button>
@else<button class="btn mt-2" name="status" value="APPROVED">Duyệt hoàn tiền</button>@endif
<button class="btn mt-2" name="status" value="REJECTED">Từ chối yêu cầu</button>
</form>
@endif
</div>
@endforeach
</article>
@empty<p>Chưa có giao dịch. Thanh toán tiền mặt hoặc chuyển khoản trực tiếp với salon.</p>@endforelse
</section>
@endsection
