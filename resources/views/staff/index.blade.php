@extends('layouts.app')
@section('title', 'Nhân viên')
@section('content')
<section class="dashboard-card">
<a href="{{ route('salon.branches') }}">← Chi nhánh</a><h1>Nhân viên · {{ $branch->name }}</h1>
@can('manageBookings', $branch)<p><a href="{{ route('salon.bookings', $branch) }}">Quản lý lịch hẹn →</a></p>@endcan
@can('update', $branch)<div class="d-flex gap-3 mb-4"><a class="btn btn-primary" href="{{ route('staff.create', $branch) }}">Thêm nhân viên</a><a class="btn" href="{{ route('schedule.edit', $branch) }}">Giờ mở cửa & ngày nghỉ</a></div>@endcan
<div class="table-responsive"><table class="table"><thead><tr><th>Nhân viên</th><th>Dịch vụ thực hiện</th><th>Nhận lịch</th><th>Thao tác</th></tr></thead><tbody>
@forelse($staffList as $member)<tr><td>{{ $member->full_name }}<br><small>{{ $member->position }}</small></td><td>{{ $member->services->pluck('name')->join(', ') ?: 'Chưa phân công' }}</td><td>{{ $member->is_bookable && $member->status === 'ACTIVE' ? 'Được phép' : 'Tạm ngừng' }}</td><td>@can('update', $branch)<a href="{{ route('staff.edit', [$branch, $member]) }}">Hồ sơ & lịch làm</a>@endcan</td></tr>
@empty<tr><td colspan="4">Chưa có nhân viên.</td></tr>@endforelse</tbody></table></div>{{ $staffList->links() }}</section>
@endsection
