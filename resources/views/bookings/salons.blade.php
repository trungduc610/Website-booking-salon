@extends('layouts.app')
@section('title','Đặt lịch làm đẹp')
@section('content')
<section class="dashboard-card"><span class="eyebrow">THỜI GIAN DÀNH CHO BẠN</span><h1>Chọn nơi chăm sóc bạn</h1>
<div class="row g-3">@forelse($branches as $branch)<div class="col-md-6"><article class="border rounded p-4"><h2 class="h4">{{ $branch->name }}</h2><p>{{ $branch->business->name }} · {{ $branch->address_line }}</p><a class="btn btn-primary" href="{{ route('bookings.create',$branch) }}">Chọn dịch vụ và thời gian</a></article></div>@empty<p>Chưa có chi nhánh đang nhận đặt lịch.</p>@endforelse</div>{{ $branches->links() }}</section>
@endsection
