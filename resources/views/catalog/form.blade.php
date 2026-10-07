@extends('layouts.app')
@section('title', $service->exists ? 'Sửa dịch vụ' : 'Thêm dịch vụ')
@section('content')
<section class="dashboard-card">
    <a href="{{ route('catalog.index', $branch) }}">← Danh sách dịch vụ</a>
    <h1>{{ $service->exists ? 'Chỉnh sửa dịch vụ' : 'Thêm dịch vụ' }}</h1>
    <p>{{ $branch->name }}</p>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($categories->isEmpty())<p role="status">Chi nhánh chưa có danh mục dịch vụ. Vui lòng liên hệ người quản trị doanh nghiệp.</p>@endif
    <form action="{{ $service->exists ? route('catalog.update', [$branch, $service]) : route('catalog.store', $branch) }}" method="post" data-submit-form>
        @csrf
        @if($service->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><label for="name" class="form-label">Tên dịch vụ</label><input class="form-control" id="name" name="name" required minlength="2" maxlength="200" value="{{ old('name', $service->name) }}"></div>
            <div class="col-md-4"><label for="category_id" class="form-label">Danh mục</label><select class="form-control" id="category_id" name="category_id" required><option value="">Chọn danh mục</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $service->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label for="price" class="form-label">Giá (VNĐ)</label><input class="form-control" type="number" min="0" max="9999999999.99" step="0.01" id="price" name="price" required value="{{ old('price', $service->price) }}"></div>
            <div class="col-md-6"><label for="duration_minutes" class="form-label">Thời lượng (phút)</label><input class="form-control" type="number" min="5" max="600" id="duration_minutes" name="duration_minutes" required value="{{ old('duration_minutes', $service->duration_minutes) }}"></div>
            <div class="col-12"><label for="description" class="form-label">Mô tả</label><textarea class="form-control" id="description" name="description" maxlength="2000" rows="4">{{ old('description', $service->description) }}</textarea></div>
            <div class="col-md-6"><label for="status" class="form-label">Trạng thái</label><select class="form-control" id="status" name="status"><option value="ACTIVE" @selected(old('status', $service->status) === 'ACTIVE')>Đang cung cấp</option><option value="INACTIVE" @selected(old('status', $service->status) === 'INACTIVE')>Ngừng cung cấp</option></select></div>
            <div class="col-md-6"><label for="bookable" class="form-label">Nhận lịch mới</label><select class="form-control" id="bookable" name="bookable"><option value="1" @selected(old('bookable', $service->bookable) == 1)>Có</option><option value="0" @selected(old('bookable', $service->bookable) == 0)>Không</option></select></div>
        </div>
        <button class="btn btn-primary mt-4" type="submit" data-submit-button @disabled($categories->isEmpty())><span data-button-label>Lưu dịch vụ</span><span class="button-spinner" aria-hidden="true" hidden></span></button>
        <p class="form-status" role="status" data-submit-status></p>
    </form>
</section>
@endsection
