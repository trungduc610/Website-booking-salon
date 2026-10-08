<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\MomoPaymentAttempt;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MomoGateway
{
    private const FINAL_FAILURES = [98, 99, 1001, 1002, 1003, 1004, 1005, 1006, 1007, 1017, 1026, 2019, 4001, 4002, 4100];

    public function enabledFor(Branch $branch): bool
    {
        return (bool) config('momo.enabled') && $branch->business_id === (int) config('momo.business_id')
            && filled(config('momo.partner_code')) && filled(config('momo.access_key')) && filled(config('momo.secret_key'))
            && in_array(config('momo.environment'), ['sandbox', 'production'], true)
            && str_starts_with((string) config('app.url'), 'https://');
    }

    private function endpoint(string $environment): string
    {
        return $environment === 'production' ? 'https://payment.momo.vn/v2/gateway/api/' : 'https://test-payment.momo.vn/v2/gateway/api/';
    }

    public function sign(array $fields): string
    {
        ksort($fields);
        $raw = collect($fields)->map(fn ($value, $key) => $key.'='.$value)->implode('&');
        return hash_hmac('sha256', $raw, (string) config('momo.secret_key'));
    }

    public function checkout(User $user, Booking $booking): string
    {
        [$attempt, $created] = DB::transaction(function () use ($user, $booking): array {
            $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $booking->customer_id === $user->customerProfile()->value('id'), 403);
            abort_unless($this->enabledFor($branch), 503);
            if (!in_array($booking->status, ['CONFIRMED', 'CHECKED_IN', 'IN_PROGRESS', 'COMPLETED'], true)) {
                $this->fail('Chỉ thanh toán MoMo sau khi salon xác nhận lịch hẹn.');
            }
            $pending = Payment::where('booking_id', $booking->id)->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])->first();
            if ($pending) {
                $attempt = MomoPaymentAttempt::where('payment_id', $pending->id)->first();
                if (!$attempt || $attempt->partner_code !== config('momo.partner_code') || $attempt->environment !== config('momo.environment')) {
                    $this->fail('Có giao dịch chờ đối soát. Vui lòng liên hệ salon.');
                }
                return [$attempt, false];
            }
            $cents = app(PaymentManager::class)->totals($booking)['remaining'];
            if ($cents % 100 !== 0 || $cents < 100000 || $cents > 5000000000) {
                $this->fail('MoMo hỗ trợ số tiền nguyên từ 1.000 ₫ đến 50.000.000 ₫. Vui lòng thanh toán trực tiếp nếu số dư ngoài giới hạn.');
            }
            $payment = Payment::create(['booking_id' => $booking->id, 'amount' => VoucherDiscount::decimal($cents),
                'method' => 'MOMO', 'status' => 'PENDING', 'request_token' => (string) Str::uuid(), 'recorded_by' => $user->id]);
            $attempt = MomoPaymentAttempt::create(['payment_id' => $payment->id, 'order_id' => 'GB-'.Str::uuid(),
                'request_id' => (string) Str::uuid(), 'partner_code' => config('momo.partner_code'),
                'environment' => config('momo.environment'), 'order_info' => 'GlowBook '.$booking->booking_code]);
            return [$attempt, true];
        }, 3);
        if (!$created) {
            if ($attempt->pay_url) {
                return $this->safeUrl($attempt->pay_url, $attempt->environment);
            }
            $this->fail('Giao dịch đang chờ đối soát. Bấm Cập nhật trạng thái MoMo; không tạo thanh toán mới.');
        }
        $payment = Payment::findOrFail($attempt->payment_id);
        $base = rtrim(config('app.url'), '/');
        $payload = [
            'partnerCode' => $attempt->partner_code, 'requestId' => $attempt->request_id, 'orderId' => $attempt->order_id,
            'amount' => intdiv(VoucherDiscount::cents($payment->amount), 100), 'orderInfo' => $attempt->order_info,
            'redirectUrl' => $base.route('payments.show', $booking, false), 'ipnUrl' => $base.route('momo.ipn', [], false),
            'requestType' => 'captureWallet', 'extraData' => '',
        ];
        $payload['signature'] = $this->sign(['accessKey' => config('momo.access_key'), ...$payload]);
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(35)->withoutRedirecting()
                ->post($this->endpoint($attempt->environment).'create', [...$payload, 'lang' => 'vi', 'autoCapture' => true]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $this->fail('Chưa nhận được phản hồi MoMo. Giao dịch được giữ để đối soát, không thanh toán lại.');
        }
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || ($body['orderId'] ?? '') !== $attempt->order_id
            || ($body['requestId'] ?? '') !== $attempt->request_id || ($body['partnerCode'] ?? '') !== $attempt->partner_code
            || (string) ($body['amount'] ?? '') !== (string) $payload['amount']) {
            $this->fail('Chưa xác minh được phản hồi MoMo. Vui lòng cập nhật trạng thái để đối soát.');
        }
        if (($body['resultCode'] ?? null) !== 0) {
            $this->apply($attempt, $body);
            $this->fail('MoMo chưa tạo được liên kết. Vui lòng kiểm tra trạng thái giao dịch.');
        }
        $url = $this->safeUrl((string) ($body['payUrl'] ?? ''), $attempt->environment);
        $attempt->update(['pay_url' => $url]);
        return $url;
    }

    private function safeUrl(string $url, string $environment): string
    {
        $host = $environment === 'production' ? 'payment.momo.vn' : 'test-payment.momo.vn';
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== $host || parse_url($url, PHP_URL_USER) !== null) {
            $this->fail('Liên kết MoMo không hợp lệ. Vui lòng cập nhật trạng thái giao dịch.');
        }
        return $url;
    }

    public function notify(array $data): void
    {
        $attempt = MomoPaymentAttempt::where('order_id', $data['orderId'])->firstOrFail();
        $payment = Payment::findOrFail($attempt->payment_id);
        $booking = Booking::withTrashed()->findOrFail($payment->booking_id);
        abort_unless((int) $booking->branch()->withTrashed()->value('business_id') === (int) config('momo.business_id')
            && filled(config('momo.secret_key')) && $attempt->partner_code === config('momo.partner_code')
            && $attempt->environment === config('momo.environment'), 403);
        $signed = ['accessKey' => config('momo.access_key')];
        foreach (['amount','extraData','message','orderId','orderInfo','orderType','partnerCode','payType','requestId','responseTime','resultCode','transId'] as $key) {
            $signed[$key] = $data[$key];
        }
        abort_unless(hash_equals($this->sign($signed), $data['signature']), 403);
        abort_unless($data['partnerCode'] === $attempt->partner_code && $data['requestId'] === $attempt->request_id
            && $data['orderInfo'] === $attempt->order_info && $data['extraData'] === ''
            && (string) $data['amount'] === (string) intdiv(VoucherDiscount::cents($payment->amount), 100), 409);
        $this->apply($attempt, $data);
    }

    public function reconcile(User $user, Booking $booking, Payment $payment): void
    {
        $booking->load('branch');
        abort_unless($payment->booking_id === $booking->id && ($booking->customer_id === $user->customerProfile()->value('id') || $user->can('manageBookings', $booking->branch)), 403);
        abort_unless($this->enabledFor($booking->branch), 503);
        $attempt = MomoPaymentAttempt::where('payment_id', $payment->id)->firstOrFail();
        abort_unless($attempt->partner_code === config('momo.partner_code') && $attempt->environment === config('momo.environment'), 409);
        $payload = ['partnerCode' => $attempt->partner_code, 'requestId' => (string) Str::uuid(), 'orderId' => $attempt->order_id];
        $payload['signature'] = $this->sign(['accessKey' => config('momo.access_key'), ...$payload]);
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(35)->withoutRedirecting()
                ->post($this->endpoint($attempt->environment).'query', [...$payload, 'lang' => 'vi']);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $this->fail('Chưa thể đối soát MoMo. Giao dịch vẫn đang được giữ; vui lòng thử lại sau.');
        }
        $data = $response->json();
        if (!$response->successful() || !is_array($data) || ($data['orderId'] ?? '') !== $attempt->order_id
            || ($data['requestId'] ?? '') !== $payload['requestId'] || ($data['partnerCode'] ?? '') !== $attempt->partner_code
            || (string) ($data['amount'] ?? '') !== (string) intdiv(VoucherDiscount::cents($payment->amount), 100)) {
            $this->fail('Không xác minh được trạng thái MoMo. Vui lòng liên hệ salon để đối soát.');
        }
        $this->apply($attempt, $data);
    }

    private function apply(MomoPaymentAttempt $attempt, array $data): void
    {
        $payment = Payment::findOrFail($attempt->payment_id);
        $booking = Booking::withTrashed()->findOrFail($payment->booking_id);
        DB::transaction(function () use ($attempt, $payment, $booking, $data): void {
            Branch::withTrashed()->whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            Booking::withTrashed()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $attempt = MomoPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $code = $data['resultCode'] ?? null;
            if (!is_int($code)) {
                return;
            }
            // A delayed failure or duplicate success must not downgrade received/refunded money.
            if (in_array($payment->status, PaymentManager::RECEIVED, true)) {
                if ($code === 0) {
                    abort_unless($attempt->transaction_id === (string) ($data['transId'] ?? ''), 409);
                }
                return;
            }
            $attempt->result_code = $code;
            if ($code === 0) {
                $transaction = (string) ($data['transId'] ?? '');
                abort_unless(ctype_digit($transaction) && (int) $transaction > 0, 422);
                $attempt->transaction_id = $transaction;
                // Even after cancellation, received money must remain visible for refund review.
                $payment->fill(['status' => 'PAID', 'paid_at' => now(), 'transaction_ref' => $transaction]);
            } elseif (in_array($code, self::FINAL_FAILURES, true)) {
                $payment->status = 'FAILED';
            }
            $attempt->save();
            $payment->save();
        }, 3);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
