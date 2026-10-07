<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingManager;
use App\Services\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function fixture(): array
    {
        $f = $this->bookingFixture();
        $f['booking'] = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        app(BookingManager::class)->transition($f['user'], $f['booking'], 'CONFIRMED');
        $f['booking']->refresh();
        $this->actingAs($f['user']);
        return $f;
    }

    private function collect(array $f, array $extra = []): array
    {
        return array_merge(['amount' => '100.25', 'method' => 'CASH', 'request_token' => (string) Str::uuid()], $extra);
    }

    private function payment(array $f, array $extra = []): Payment
    {
        return app(PaymentManager::class)->collect($f['user'], $f['booking'], $this->collect($f, $extra));
    }

    private function refund(array $f, Payment $payment, string $amount = '100.25'): RefundRequest
    {
        return app(PaymentManager::class)->requestRefund(
            $f['user'],
            $f['booking'],
            $payment,
            ['amount' => $amount, 'reason' => 'Đổi kế hoạch', 'request_token' => (string) Str::uuid()],
            true
        );
    }

    public function test_partial_collection_exact_balance_and_replay(): void
    {
        $f = $this->fixture();
        $url = route('salon.payments.collect', [$f['branch'], $f['booking']]);
        $data = $this->collect($f);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->post($url, array_replace($data, ['amount' => '101']))->assertStatus(409);
        $this->post($url, $this->collect($f, ['amount' => '123356.53']))->assertSessionHasNoErrors();
        $this->post($url, $this->collect($f, ['amount' => '0.01']))->assertSessionHasErrors('payment');
        $this->assertSame(['received' => 12345678, 'refunded' => 0, 'net' => 12345678, 'remaining' => 0], app(PaymentManager::class)->totals($f['booking']));
    }

    public function test_invalid_amounts_and_bank_reference_are_rejected(): void
    {
        $f = $this->fixture();
        $url = route('salon.payments.collect', [$f['branch'], $f['booking']]);
        foreach (['0', '-1', '1.001', '1e3', '99999999999', '123456.79'] as $amount) {
            $this->post($url, $this->collect($f, ['amount' => $amount]))->assertSessionHasErrors();
        }
        $this->post($url, $this->collect($f, ['method' => 'BANK_TRANSFER']))->assertSessionHasErrors('transaction_ref');
        $this->post($url, $this->collect($f, ['method' => 'MOMO']))->assertSessionHasErrors('method');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_refund_reservations_rejection_and_replay(): void
    {
        $f = $this->fixture();
        $payment = $this->payment($f);
        $url = route('refunds.request', [$f['booking'], $payment]);
        $data = ['amount' => '60.00', 'reason' => 'Hoàn một phần', 'request_token' => (string) Str::uuid()];
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refund_requests', 1);
        $this->post($url, array_replace($data, ['amount' => '61']))->assertStatus(409);
        $this->post($url, array_replace($data, ['request_token' => (string) Str::uuid()]))->assertSessionHasErrors('payment');
        $refund = RefundRequest::sole();
        $review = route('salon.refunds.review', [$f['branch'], $f['booking'], $refund]);
        $this->patch($review, ['status' => 'REJECTED'])->assertSessionHasErrors('review_note');
        $this->patch($review, ['status' => 'REJECTED', 'review_note' => 'Sai số tiền'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($data, ['request_token' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refund_requests', 2);
    }

    public function test_refund_requires_approval_and_records_partial_then_full(): void
    {
        $f = $this->fixture();
        $payment = $this->payment($f);
        $refund = $this->refund($f, $payment, '40.10');
        $url = route('salon.refunds.review', [$f['branch'], $f['booking'], $refund]);
        $this->patch($url, ['status' => 'REFUNDED'])->assertSessionHasErrors('payment');
        $this->patch($url, ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->patch($url, ['status' => 'REFUNDED'])->assertSessionHasNoErrors();
        $this->patch($url, ['status' => 'REFUNDED'])->assertSessionHasNoErrors();
        $this->assertSame('PARTIALLY_REFUNDED', $payment->fresh()->status);
        $this->assertSame($f['user']->id, $refund->fresh()->processed_by);
        $this->patch($url, ['status' => 'REJECTED', 'review_note' => 'Không thể đảo'])->assertSessionHasErrors('payment');
        $second = $this->refund($f, $payment, '60.15');
        app(PaymentManager::class)->review($f['user'], $f['booking'], $second, ['status' => 'APPROVED']);
        app(PaymentManager::class)->review($f['user'], $f['booking'], $second, ['status' => 'REFUNDED']);
        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $totals = app(PaymentManager::class)->totals($f['booking']);
        $this->assertSame(10025, $totals['refunded']);
        $this->assertSame(0, $totals['net']);
        $this->assertSame(12335653, $totals['remaining']);
    }

    public function test_bank_refund_requires_transfer_reference(): void
    {
        $f = $this->fixture();
        $payment = $this->payment($f, ['method' => 'BANK_TRANSFER', 'transaction_ref' => 'IN-001']);
        $refund = $this->refund($f, $payment);
        $url = route('salon.refunds.review', [$f['branch'], $f['booking'], $refund]);
        $this->patch($url, ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->patch($url, ['status' => 'REFUNDED'])->assertSessionHasErrors('payment');
        $this->patch($url, ['status' => 'REFUNDED', 'transaction_ref' => 'OUT-001'])->assertSessionHasNoErrors();
        $this->assertSame('OUT-001', $refund->fresh()->transaction_ref);
    }

    public function test_customer_can_request_but_cannot_collect_or_review(): void
    {
        $f = $this->fixture();
        $payment = $this->payment($f);
        $refund = $this->refund($f, $payment);
        $f['user']->roles()->detach();
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertForbidden();
        $this->patch(route('salon.refunds.review', [$f['branch'], $f['booking'], $refund]), ['status' => 'APPROVED'])->assertForbidden();
        $this->get(route('payments.show', $f['booking']))->assertOk()->assertSee('Đổi kế hoạch');
    }

    public function test_receptionist_can_collect_and_request_but_cannot_approve(): void
    {
        $f = $this->fixture();
        $f['user']->roles()->detach();
        $f['user']->roles()->attach(Role::where('code', 'RECEPTIONIST')->sole()->id, ['business_id' => $f['business']->id, 'branch_id' => $f['branch']->id]);
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertSessionHasNoErrors();
        $p = Payment::sole();
        $this->post(route('salon.refunds.request', [$f['branch'], $f['booking'], $p]), ['amount' => '10', 'reason' => 'Hoàn', 'request_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $r = RefundRequest::sole();
        $this->patch(route('salon.refunds.review', [$f['branch'], $f['booking'], $r]), ['status' => 'APPROVED'])->assertForbidden();
    }

    public function test_other_customer_cannot_view_or_refund(): void
    {
        $f = $this->fixture();
        $p = $this->payment($f);
        $other = User::create(['full_name' => 'Other', 'email' => 'other@example.test', 'password_hash' => 'unused']);
        $other->customerProfile()->create();
        $this->actingAs($other)->get(route('payments.show', $f['booking']))->assertForbidden();
        $this->post(route('refunds.request', [$f['booking'], $p]), ['amount' => '1', 'reason' => 'X', 'request_token' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_nested_resources_and_wrong_branch_are_rejected(): void
    {
        $f = $this->fixture();
        $p = $this->payment($f);
        $r = $this->refund($f, $p);
        $otherBooking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '14:00']));
        $this->post(route('refunds.request', [$otherBooking, $p]), ['amount' => '1', 'reason' => 'X', 'request_token' => (string) Str::uuid()])->assertNotFound();
        $this->patch(route('salon.refunds.review', [$f['branch'], $otherBooking, $r]), ['status' => 'APPROVED'])->assertNotFound();
        $branch = $f['branch']->replicate();
        $branch->name = 'Other branch';
        $branch->save();
        $this->get(route('salon.payments.show', [$branch, $f['booking']]))->assertNotFound();
        $this->post(route('salon.payments.collect', [$branch, $f['booking']]), $this->collect($f))->assertNotFound();
    }

    public function test_cancelled_paid_booking_retains_money_until_refunded(): void
    {
        $f = $this->fixture();
        $p = $this->payment($f);
        app(BookingManager::class)->transition($f['user'], $f['booking'], 'CANCELLED', true);
        $this->assertSame('PAID', $p->fresh()->status);
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertSessionHasErrors('payment');
        $this->refund($f, $p);
        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_pending_expired_rejected_and_no_show_bookings_cannot_collect(): void
    {
        $f = $this->fixture();
        foreach (['PENDING', 'EXPIRED', 'REJECTED', 'NO_SHOW'] as $status) {
            $f['booking']->update(['status' => $status]);
            $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertSessionHasErrors('payment');
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_discounted_price_caps_collection_and_zero_price_needs_no_payment(): void
    {
        $f = $this->bookingFixture();
        $v = Voucher::create(['business_id' => $f['business']->id, 'code' => 'PAY10', 'name' => 'Discount', 'discount_type' => 'PERCENTAGE',
            'discount_value' => '10', 'min_order_value' => '0', 'total_quantity' => 10, 'max_usage_per_customer' => 2,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(), 'status' => 'ACTIVE']);
        $f['booking'] = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['voucher_code' => 'PAY10']));
        app(BookingManager::class)->transition($f['user'], $f['booking'], 'CONFIRMED');
        $this->actingAs($f['user']);
        $url = route('salon.payments.collect', [$f['branch'], $f['booking']]);
        $this->post($url, $this->collect($f, ['amount' => '123456.78']))->assertSessionHasErrors('payment');
        $this->post($url, $this->collect($f, ['amount' => '111111.11']))->assertSessionHasNoErrors();
        $v->update(['discount_value' => '100']);
        $zero = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '14:00', 'voucher_code' => 'PAY10']));
        app(BookingManager::class)->transition($f['user'], $zero, 'CONFIRMED');
        $this->post(route('salon.payments.collect', [$f['branch'], $zero]), $this->collect($f))->assertSessionHasErrors('payment');
    }

    public function test_full_refund_does_not_reopen_collection(): void
    {
        $f = $this->fixture();
        $p = $this->payment($f, ['amount' => '123456.78']);
        $r = $this->refund($f, $p, '123456.78');
        app(PaymentManager::class)->review($f['user'], $f['booking'], $r, ['status' => 'APPROVED']);
        app(PaymentManager::class)->review($f['user'], $f['booking'], $r, ['status' => 'REFUNDED']);
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertSessionHasErrors('payment');
        $this->post(route('refunds.request', [$f['booking'], $p]), ['amount' => '0.01', 'reason' => 'Extra', 'request_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->assertSame(0, app(PaymentManager::class)->totals($f['booking'])['remaining']);
    }

    public function test_expired_roles_and_inactive_accounts_cannot_collect(): void
    {
        $f = $this->fixture();
        $f['user']->roles()->updateExistingPivot(Role::where('code', 'BUSINESS_OWNER')->sole()->id, ['expires_at' => now()->subMinute()]);
        $url = route('salon.payments.collect', [$f['branch'], $f['booking']]);
        $this->post($url, $this->collect($f))->assertForbidden();
        $f['user']->roles()->updateExistingPivot(Role::where('code', 'BUSINESS_OWNER')->sole()->id, ['expires_at' => null]);
        $f['user']->forceFill(['is_active' => false])->save();
        $this->post($url, $this->collect($f))->assertRedirect(route('login'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_pending_payments_block_new_collection(): void
    {
        $f = $this->fixture();
        Payment::create(['booking_id' => $f['booking']->id, 'amount' => '100', 'method' => 'CASH', 'status' => 'PENDING']);
        $this->post(route('salon.payments.collect', [$f['branch'], $f['booking']]), $this->collect($f))->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_pages_render_payment_and_approved_refund_forms(): void
    {
        $f = $this->fixture();
        $p = $this->payment($f);
        $r = $this->refund($f, $p, '20');
        app(PaymentManager::class)->review($f['user'], $f['booking'], $r, ['status' => 'APPROVED']);
        $this->get(route('salon.payments.show', [$f['branch'], $f['booking']]))->assertOk()->assertSee('Xác nhận đã trả tiền')->assertSee('Thực giữ sau hoàn');
        $this->get(route('payments.show', $f['booking']))->assertOk()->assertDontSee('Xác nhận đã trả tiền');
        $this->get(route('salon.bookings', $f['branch']))->assertOk()->assertSee('Thanh toán và hoàn tiền');
        $this->get(route('bookings.show', $f['booking']))->assertOk()->assertSee('Thanh toán và hoàn tiền');
    }
}
