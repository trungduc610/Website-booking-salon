<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('glowbook:create-salon {email} {--name=} {--branch=Chi nhánh chính}', function (): int {
    $data = ['email' => mb_strtolower($this->argument('email')), 'name' => $this->option('name'), 'branch' => $this->option('branch')];
    $validator = Validator::make($data, ['email' => 'required|email', 'name' => 'required|string|max:200', 'branch' => 'required|string|max:200']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());
        return 1;
    }
    $user = User::where('email', $data['email'])->where('is_active', true)->first();
    if (! $user) {
        $this->error('Cần một tài khoản đang hoạt động. Hãy đăng ký tài khoản trước.');
        return 1;
    }
    $branch = DB::transaction(function () use ($user, $data): Branch {
        $role = Role::where('code', 'BUSINESS_OWNER')->sole();
        $owner = DB::table('business_owner_profiles')->where('user_id', $user->id)->first();
        if ($owner && $owner->deleted_at !== null) {
            throw new RuntimeException('Hồ sơ chủ salon đã bị xóa mềm; cần kiểm tra trước khi cấp quyền.');
        }
        $ownerId = $owner?->id ?? DB::table('business_owner_profiles')->insertGetId(['user_id' => $user->id]);
        $business = Business::create(['owner_id' => $ownerId, 'name' => $data['name'], 'slug' => Str::limit(Str::slug($data['name']), 170, '').'-'.Str::lower(Str::random(12))]);
        $branch = Branch::create(['business_id' => $business->id, 'name' => $data['branch']]);
        ServiceCategory::create(['business_id' => $business->id, 'name' => 'Dịch vụ chung', 'slug' => 'dich-vu-chung']);
        $user->roles()->attach($role->id, ['business_id' => $business->id, 'granted_by' => $user->id, 'granted_at' => now()]);
        return $branch;
    });
    $this->info('Đã tạo salon ở trạng thái chờ duyệt và cấp quyền cho '.$user->email.'. Chi nhánh #'.$branch->id);
    return 0;
})->purpose('Tạo salon chờ duyệt và cấp quyền chủ salon cho tài khoản đã tồn tại (thao tác quản trị CLI).');

Artisan::command('glowbook:expire-bookings', function (): int {
    $count = 0;
    $candidates = \App\Models\Booking::where('status', 'PENDING')->where('pending_expires_at', '<=', now())->limit(200)->get(['id','branch_id']);
    foreach ($candidates as $candidate) {
        $count += DB::transaction(function () use ($candidate): int {
            Branch::whereKey($candidate->branch_id)->lockForUpdate()->firstOrFail();
            $booking = \App\Models\Booking::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $booking || $booking->status !== 'PENDING' || ! $booking->pending_expires_at || now()->lt($booking->pending_expires_at)) {
                return 0;
            }
            app(\App\Services\VoucherDiscount::class)->release($booking);
            $booking->update(['status' => 'EXPIRED']);
            $booking->items()->update(['status' => 'CANCELLED']);
            DB::table('booking_status_histories')->insert(['booking_id' => $booking->id, 'status' => 'EXPIRED', 'note' => 'Hết hạn giữ chỗ tự động']);
            return 1;
        }, 3);
    }
    $this->info('Đã hết hạn '.$count.' lịch hẹn.');
    return 0;
})->purpose('Hết hạn tối đa 200 lịch chờ xác nhận trong mỗi lượt.');

\Illuminate\Support\Facades\Schedule::command('glowbook:expire-bookings')->everyMinute()->withoutOverlapping();

Artisan::command('glowbook:publish-salon {branch}', function (): int {
    $branch = Branch::findOrFail($this->argument('branch'));
    return DB::transaction(function () use ($branch): int {
        $branch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
        $open = DB::table('branch_working_hours')->where('branch_id', $branch->id)->where('is_closed', false)->exists();
        $staff = $branch->staff()->where('status', 'ACTIVE')->where('is_bookable', true)->whereHas('services')
            ->whereHas('hours', fn ($q) => $q->where('is_off', false))->exists();
        if (! $open || ! $staff) {
            $this->error('Cần giờ mở cửa và nhân viên có lịch làm, dịch vụ trước khi xuất bản.');
            return 1;
        }
        $branch->business()->firstOrFail()->update(['status' => 'ACTIVE']);
        $branch->update(['status' => 'ACTIVE','operational_status' => 'ACTIVE','review_status' => 'APPROVED','published_at' => now()]);
        $this->info('Chi nhánh #'.$branch->id.' đã nhận đặt lịch trực tuyến.');
        return 0;
    });
})->purpose('Thao tác quản trị CLI: xuất bản chi nhánh đã cấu hình.');
