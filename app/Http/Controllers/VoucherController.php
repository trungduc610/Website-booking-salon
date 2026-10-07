<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    private function authorizeOwner(Request $request, Branch $branch): void
    {
        abort_unless($request->user()->can('view', $branch) && ($request->user()->hasRole('PLATFORM_ADMIN')
            || $request->user()->hasRole('BUSINESS_OWNER', $branch->business_id)), 403);
    }

    public function index(Request $request, Branch $branch)
    {
        $this->authorizeOwner($request, $branch);
        return view('vouchers.index', ['branch' => $branch,
            'vouchers' => Voucher::where('business_id', $branch->business_id)->latest()->paginate(15)]);
    }

    public function store(Request $request, Branch $branch)
    {
        $this->authorizeOwner($request, $branch);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'unique:vouchers,code'],
            'name' => ['required', 'string', 'max:200'],
            'discount_type' => ['required', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'discount_value' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', $request->input('discount_type') === 'PERCENTAGE' ? 'max:100' : 'max:9999999999.99'],
            'min_order_value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'max_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'total_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'max_usage_per_customer' => ['required', 'integer', 'min:1', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_date' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_date'],
        ]);
        foreach (['start_date', 'end_date'] as $field) {
            $data[$field] = \Carbon\CarbonImmutable::parse($data[$field], config('app.timezone'))->format('Y-m-d H:i:s');
        }
        try {
            Voucher::create([...$data, 'business_id' => $branch->business_id, 'used_quantity' => 0, 'status' => 'ACTIVE', 'created_by_platform' => 0]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Mã ưu đãi đã được sử dụng.']);
        }
        return back()->with('success', 'Đã tạo ưu đãi dùng chung các chi nhánh trong doanh nghiệp.');
    }

    public function toggle(Request $request, Branch $branch, Voucher $voucher)
    {
        $this->authorizeOwner($request, $branch);
        abort_unless($voucher->business_id === $branch->business_id, 404);
        $data = $request->validate(['status' => ['required', 'in:ACTIVE,INACTIVE']]);
        $voucher->update($data);
        return back()->with('success', 'Đã cập nhật trạng thái ưu đãi.');
    }
}
