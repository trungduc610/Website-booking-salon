<?php

namespace Tests\Feature;

use App\Models\MomoPaymentAttempt;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingManager;
use App\Services\MomoGateway;
use App\Services\VoucherDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class MomoTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function fixture(): array
    {
        $f = $this->bookingFixture();
        $f['service']->update(['price' => '350000.00']);
        $f['branch']->update(['booking_confirmation_mode' => 'AUTO_CONFIRMATION']);
        $f['booking'] = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        config(['app.url' => 'https://glowbook.example.test', 'momo.enabled' => true, 'momo.environment' => 'sandbox',
            'momo.business_id' => $f['branch']->business_id, 'momo.partner_code' => 'TESTPARTNER', 'momo.access_key' => 'test-access', 'momo.secret_key' => 'test-secret']);
        $this->actingAs($f['user']);
        return $f;
    }

    private function fakeCreate(string $url = 'https://test-payment.momo.vn/v2/gateway/pay?token=fixture'): void
    {
        Http::fake(['*/create' => fn ($request) => Http::response(['partnerCode' => $request['partnerCode'], 'requestId' => $request['requestId'],
            'orderId' => $request['orderId'], 'amount' => $request['amount'], 'resultCode' => 0, 'payUrl' => $url])]);
    }

    private function notification(int $code = 0): array
    {
        $attempt = MomoPaymentAttempt::sole();
        $data = ['partnerCode' => $attempt->partner_code, 'requestId' => $attempt->request_id, 'orderId' => $attempt->order_id,
            'amount' => intdiv(VoucherDiscount::cents(Payment::sole()->amount), 100), 'orderInfo' => $attempt->order_info,
            'orderType' => 'momo_wallet', 'payType' => 'qr', 'extraData' => '', 'message' => 'Result',
            'responseTime' => 1791450000000, 'resultCode' => $code, 'transId' => 999001];
        $data['signature'] = app(MomoGateway::class)->sign(['accessKey' => config('momo.access_key'), ...$data]);
        return $data;
    }

    public function test_checkout_uses_server_balance_and_reuses_pending_attempt(): void
    {
        $f = $this->fixture();
        $this->fakeCreate();
        $this->post(route('momo.checkout', $f['booking']), ['amount' => 1])->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay?token=fixture');
        $this->post(route('momo.checkout', $f['booking']))->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['method' => 'MOMO', 'amount' => '350000.00', 'status' => 'PENDING']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === 350000 && $request['autoCapture'] === true && str_starts_with($request['ipnUrl'], 'https://glowbook.example.test/'));
        $this->get(route('payments.show', [$f['booking'], 'resultCode' => 0]))->assertOk();
        $this->assertSame('PENDING', Payment::sole()->status);
    }

    public function test_signed_callback_is_idempotent_and_checks_amount_and_signature(): void
    {
        $f = $this->fixture();
        $this->fakeCreate();
        $this->post(route('momo.checkout', $f['booking']));
        $data = $this->notification();
        $this->postJson(route('momo.ipn'), [...$data, 'signature' => str_repeat('0', 64)])->assertForbidden();
        $wrongAmount = [...$data, 'amount' => 1];
        unset($wrongAmount['signature']);
        $wrongAmount['signature'] = app(MomoGateway::class)->sign(['accessKey' => config('momo.access_key'), ...$wrongAmount]);
        $this->postJson(route('momo.ipn'), $wrongAmount)->assertConflict();
        $this->postJson(route('momo.ipn'), $data)->assertNoContent();
        $this->postJson(route('momo.ipn'), $data)->assertNoContent();
        $this->postJson(route('momo.ipn'), $this->notification(1006))->assertNoContent();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('PAID', Payment::sole()->status);
        $this->assertSame('999001', Payment::sole()->transaction_ref);
    }

    public function test_timeout_preserves_pending_attempt_and_blocks_recollection(): void
    {
        $f = $this->fixture();
        Http::fake(fn () => throw new ConnectionException('Simulated timeout'));
        $this->post(route('momo.checkout', $f['booking']))->assertSessionHasErrors('payment');
        $this->post(route('momo.checkout', $f['booking']))->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('PENDING', Payment::sole()->status);
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), ['amount' => '350000', 'method' => 'CASH', 'request_token' => (string) \Illuminate\Support\Str::uuid()])->assertSessionHasErrors('payment');
    }

    public function test_query_reconciles_authoritative_status_and_late_success_after_cancellation(): void
    {
        $f = $this->fixture();
        $this->fakeCreate();
        $this->post(route('momo.checkout', $f['booking']));
        app(BookingManager::class)->transition($f['user'], $f['booking'], 'CANCELLED', true);
        Http::fake(['*/query' => fn ($request) => Http::response(['partnerCode' => $request['partnerCode'], 'requestId' => $request['requestId'],
            'orderId' => $request['orderId'], 'amount' => 350000, 'resultCode' => 0, 'transId' => 999001])]);
        $this->post(route('momo.reconcile', [$f['booking'], Payment::sole()]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PAID', Payment::sole()->status);
        $this->assertSame('CANCELLED', $f['booking']->fresh()->status);
    }

    public function test_disabled_tenant_and_other_customer_cannot_start_checkout(): void
    {
        $f = $this->fixture();
        Http::preventStrayRequests();
        config(['momo.business_id' => $f['branch']->business_id + 1]);
        $this->post(route('momo.checkout', $f['booking']))->assertStatus(503);
        config(['momo.business_id' => $f['branch']->business_id]);
        $other = User::create(['full_name' => 'Other', 'email' => 'momo-other@example.test', 'password_hash' => $f['user']->password_hash]);
        $other->customerProfile()->create();
        $this->actingAs($other)->post(route('momo.checkout', $f['booking']))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unsafe_provider_redirect_is_rejected(): void
    {
        $f = $this->fixture();
        $this->fakeCreate('https://attacker.example/pay');
        $this->post(route('momo.checkout', $f['booking']))->assertSessionHasErrors('payment');
        $this->assertNull(MomoPaymentAttempt::sole()->pay_url);
        $this->assertSame('PENDING', Payment::sole()->status);
    }
}
