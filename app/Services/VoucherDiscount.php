<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

class VoucherDiscount
{
    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    // Called only inside the booking transaction, after its branch lock.
    public function reserve(?string $code, Branch $branch, int $customerId, int $total): array
    {
        if (! $code) {
            return [null, 0];
        }
        $voucher = Voucher::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
        if (! $voucher || $voucher->status !== 'ACTIVE'
            || ($voucher->business_id !== null && $voucher->business_id !== $branch->business_id)
            || now()->lt($voucher->start_date) || now()->gt($voucher->end_date)
            || $total < self::cents($voucher->min_order_value)
            || $voucher->used_quantity >= $voucher->total_quantity) {
            $this->invalid();
        }
        $used = Booking::withTrashed()->where('voucher_id', $voucher->id)->where('customer_id', $customerId)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED', 'EXPIRED'])->count();
        if ($used >= $voucher->max_usage_per_customer) {
            $this->invalid();
        }
        $value = self::cents($voucher->discount_value);
        $discount = $voucher->discount_type === 'PERCENTAGE' ? intdiv($total * $value, 10000) : $value;
        if ($voucher->max_discount !== null) {
            $discount = min($discount, self::cents($voucher->max_discount));
        }
        $discount = max(0, min($discount, $total));
        $voucher->increment('used_quantity');
        return [$voucher->id, $discount];
    }

    public function release(Booking $booking): void
    {
        if ($booking->voucher_id) {
            $voucher = Voucher::withTrashed()->whereKey($booking->voucher_id)->lockForUpdate()->first();
            if ($voucher && $voucher->used_quantity > 0) {
                $voucher->decrement('used_quantity');
            }
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['voucher_code' => 'Mã ưu đãi không khả dụng, hết lượt hoặc chưa đủ điều kiện.']);
    }
}
