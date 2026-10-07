@extends('layouts.app')
@section('title', 'Dịch vụ')
@section('content')
<section class="dashboard-card">
    <a href="{{ route('salon.branches') }}">← Chi nhánh</a>
    <h1>Dịch vụ · {{ $branch->name }}</h1>
    <div class="d-flex gap-3 flex-wrap justify-content-between mb-4">
        <form method="get" class="d-flex gap-2"><label for="q" class="visually-hidden">Tên dịch vụ</label><input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="200" placeholder="Tìm theo tên dịch vụ"><button class="btn">Tìm</button></form>
        @can('update', $branch)<a class="btn btn-primary" href="{{ route('catalog.create', $branch) }}">Thêm dịch vụ</a>@endcan
    </div>
    <div class="table-responsive"><table class="table align-middle">
        <caption>Danh sách dịch vụ tại {{ $branch->name }}</caption>
        <thead><tr><th>Dịch vụ</th><th>Danh mục</th><th>Giá</th><th>Thời lượng</th><th>Trạng thái</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead>
        <tbody>@forelse($services as $service)<tr>
            <td>{{ $service->name }}</td><td>{{ $service->category?->name ?? 'Danh mục đã ngừng sử dụng' }}</td>
            <td>{{ number_format((float) $service->price, 2, ',', '.') }} ₫</td><td>{{ $service->duration_minutes }} phút</td>
            <td>{{ $service->status === 'ACTIVE' ? 'Đang cung cấp' : 'Ngừng cung cấp' }}@unless($service->bookable)<br><small>Không nhận lịch mới</small>@endunless</td>
            <td>@can('update', $branch)<a href="{{ route('catalog.edit', [$branch, $service]) }}">Chỉnh sửa</a>@endcan</td>
        </tr>@empty<tr><td colspan="6">Chưa có dịch vụ phù hợp.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $services->links() }}
</section>
@endsection
