@extends('layouts.app')
@section('title', 'Chi nhánh')
@section('content')
<section class="dashboard-card">
    <span class="eyebrow">KHÔNG GIAN SALON</span><h1>Chi nhánh của bạn</h1>
    <div class="row g-3">
    @forelse($branches as $branch)
        <div class="col-md-6"><article class="border rounded p-3"><h2 class="h5">{{ $branch->name }}</h2><p>{{ $branch->business->name }}</p><a href="{{ route('catalog.index', $branch) }}">Quản lý dịch vụ →</a><br><a href="{{ route('staff.index', $branch) }}">Nhân viên và lịch làm →</a>@if(auth()->user()->hasRole('PLATFORM_ADMIN') || auth()->user()->hasRole('BUSINESS_OWNER',$branch->business_id))<br><a href="{{ route('vouchers.index',$branch) }}">Ưu đãi doanh nghiệp →</a>@endif</article></div>
    @empty
        <p>Bạn chưa được cấp quyền truy cập chi nhánh nào.</p>
    @endforelse
    </div>
    <div class="mt-4">{{ $branches->links() }}</div>
</section>
@endsection
