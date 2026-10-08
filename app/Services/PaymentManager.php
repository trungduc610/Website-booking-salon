<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentManager
{
    public const RECEIVED = ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'];
    public const RESERVED = ['PENDING', 'APPROVED', 'PROCESSING', 'REFUNDED'];

    // Gross receipts cap collection at the original discounted price. Refunds never reopen the debt.
    public function totals(Booking $booking): array
    {
        $payments = Payment::with('refunds')->where('booking_id', $booking->id)->whereIn('status', self::RECEIVED)->get();
        $received = $payments->sum(fn ($p) => VoucherDiscount::cents($p->amount));
        $refunded = $payments->sum(fn ($p) => $p->refunds->where('status', 'REFUNDED')->sum(fn ($r) => VoucherDiscount::cents($r->amount)));
        return ['received' => $received, 'refunded' => $refunded, 'net' => $received - $refunded,
            'remaining' => max(0, VoucherDiscount::cents((string) $booking->final_amount) - $received)];
    }

    public function collect(User $user, Booking $booking, array $data): Payment
    {
        $data = Validator::make($data, [
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'method' => ['required', 'in:CASH,BANK_TRANSFER'],
            'transaction_ref' => ['nullable', 'required_if:method,BANK_TRANSFER', 'string', 'max:200'],
            'request_token' => ['required', 'uuid'],
        ])->validate();
        return DB::transaction(function () use ($user, $booking, $data): Payment {
            [$branch, $booking] = $this->lock($booking);
            abort_unless($user->can('manageBookings', $branch), 403);
            $amount = VoucherDiscount::cents($data['amount']);
            $existing = Payment::where('request_token', $data['request_token'])->first();
            if ($existing) {
                abort_unless($existing->booking_id === $booking->id && $existing->recorded_by === $user->id
                    && VoucherDiscount::cents($existing->amount) === $amount && $existing->method === $data['method']
                    && $existing->transaction_ref === ($data['transaction_ref'] ?? null), 409);
                return $existing;
            }
            if (!in_array($booking->status, ['CONFIRMED', 'CHECKED_IN', 'IN_PROGRESS', 'COMPLETED'], true)) {
                $this->invalid('Chỉ thu tiền cho lịch đã xác nhận hoặc đã thực hiện.');
            }
            // Unsupported legacy pending/partial records require reconciliation, never silently recollect.
            if (Payment::where('booking_id', $booking->id)->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])->exists()) {
                $this->invalid('Lịch có giao dịch cũ chưa đối soát; chưa thể thu thêm.');
            }
            if ($amount <= 0 || $amount > $this->totals($booking)['remaining']) {
                $this->invalid('Số tiền phải lớn hơn 0 và không vượt phần còn phải thu.');
            }
            return Payment::create(['booking_id' => $booking->id, 'amount' => VoucherDiscount::decimal($amount),
                'method' => $data['method'], 'status' => 'PAID', 'paid_at' => now(), 'recorded_by' => $user->id,
                'transaction_ref' => $data['transaction_ref'] ?? null, 'request_token' => $data['request_token']]);
        }, 3);
    }

    public function requestRefund(User $user, Booking $booking, Payment $payment, array $data, bool $customer): RefundRequest
    {
        $data = Validator::make($data, ['amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:2000'], 'request_token' => ['required', 'uuid']])->validate();
        return DB::transaction(function () use ($user, $booking, $payment, $data, $customer): RefundRequest {
            [$branch, $booking] = $this->lock($booking);
            if ($customer) {
                $profile = $user->customerProfile()->value('id');
                abort_unless($user->is_active && $profile !== null && $booking->customer_id === $profile, 403);
            } else {
                abort_unless($user->can('manageBookings', $branch), 403);
            }
            $payment = Payment::where('booking_id', $booking->id)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $amount = VoucherDiscount::cents($data['amount']);
            $existing = RefundRequest::where('request_token', $data['request_token'])->first();
            if ($existing) {
                abort_unless($existing->payment_id === $payment->id && $existing->requested_by === $user->id
                    && VoucherDiscount::cents($existing->amount) === $amount && $existing->reason === $data['reason'], 409);
                return $existing;
            }
            $reserved = $payment->refunds()->whereIn('status', self::RESERVED)->get()->sum(fn ($r) => VoucherDiscount::cents($r->amount));
            if (!in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED'], true)
                || $amount <= 0 || $amount > VoucherDiscount::cents($payment->amount) - $reserved) {
                $this->invalid('Số tiền hoàn vượt số tiền khả dụng hoặc giao dịch không thể hoàn.');
            }
            return RefundRequest::create(['payment_id' => $payment->id, 'amount' => VoucherDiscount::decimal($amount),
                'reason' => $data['reason'], 'requested_by' => $user->id, 'status' => 'PENDING', 'request_token' => $data['request_token']]);
        }, 3);
    }

    public function review(User $user, Booking $booking, RefundRequest $refund, array $data): void
    {
        $data = Validator::make($data, ['status' => ['required', 'in:APPROVED,REJECTED,REFUNDED'],
            'review_note' => ['nullable', 'required_if:status,REJECTED', 'string', 'max:2000'],
            'transaction_ref' => ['nullable', 'string', 'max:200']])->validate();
        DB::transaction(function () use ($user, $booking, $refund, $data): void {
            [$branch, $booking] = $this->lock($booking);
            abort_unless($user->can('update', $branch), 403);
            $payment = Payment::where('booking_id', $booking->id)->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $refund = RefundRequest::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === $data['status']) {
                return;
            }
            $allowed = ['PENDING' => ['APPROVED', 'REJECTED'], 'APPROVED' => ['REFUNDED', 'REJECTED']];
            if (!in_array($data['status'], $allowed[$refund->status] ?? [], true)) {
                $this->invalid('Yêu cầu hoàn tiền đã đổi trạng thái. Hãy tải lại trang.');
            }
            if ($data['status'] === 'REFUNDED') {
                if (in_array($payment->method, ['BANK_TRANSFER', 'MOMO'], true) && empty($data['transaction_ref'])) {
                    $this->invalid('Cần mã giao dịch hoàn tiền qua ngân hàng hoặc MoMo.');
                }
                $total = $payment->refunds()->where('status', 'REFUNDED')->get()->sum(fn ($r) => VoucherDiscount::cents($r->amount))
                    + VoucherDiscount::cents($refund->amount);
                if (!in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED'], true) || $total > VoucherDiscount::cents($payment->amount)) {
                    $this->invalid('Không thể hoàn vượt số tiền đã thu.');
                }
                $refund->fill(['processed_by' => $user->id, 'processed_at' => now(), 'transaction_ref' => $data['transaction_ref'] ?? null]);
                $payment->update(['status' => $total === VoucherDiscount::cents($payment->amount) ? 'REFUNDED' : 'PARTIALLY_REFUNDED']);
            } else {
                $refund->fill(['reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);
            }
            $refund->status = $data['status'];
            $refund->save();
        }, 3);
    }

    // Same order as BookingManager, preventing cancellation/collection races.
    private function lock(Booking $booking): array
    {
        $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
        return [$branch, Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail()];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
