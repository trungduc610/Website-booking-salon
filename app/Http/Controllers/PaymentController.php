<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Services\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function show(Request $request, Booking $booking, PaymentManager $manager)
    {
        $profile = $request->user()->customerProfile()->value('id');
        abort_unless($profile !== null && $booking->customer_id === $profile, 403);
        return $this->page($booking, $manager, false);
    }

    public function salon(Branch $branch, Booking $booking, PaymentManager $manager)
    {
        $this->authorizeBranch($branch, $booking);
        return $this->page($booking, $manager, true);
    }

    public function collect(Request $request, Branch $branch, Booking $booking, PaymentManager $manager)
    {
        $this->authorizeBranch($branch, $booking);
        $manager->collect($request->user(), $booking, $request->all());
        return back()->with('success', 'Đã ghi nhận tiền thực nhận.');
    }

    public function requestRefund(Request $request, Booking $booking, Payment $payment, PaymentManager $manager)
    {
        $manager->requestRefund($request->user(), $booking, $payment, $request->all(), true);
        return back()->with('success', 'Đã gửi yêu cầu hoàn tiền. Salon sẽ xét duyệt.');
    }

    public function salonRefund(Request $request, Branch $branch, Booking $booking, Payment $payment, PaymentManager $manager)
    {
        $this->authorizeBranch($branch, $booking);
        $manager->requestRefund($request->user(), $booking, $payment, $request->all(), false);
        return back()->with('success', 'Đã tạo yêu cầu hoàn tiền.');
    }

    public function review(Request $request, Branch $branch, Booking $booking, RefundRequest $refund, PaymentManager $manager)
    {
        $this->authorizeBranch($branch, $booking);
        $manager->review($request->user(), $booking, $refund, $request->all());
        return back()->with('success', 'Đã cập nhật yêu cầu hoàn tiền.');
    }

    private function authorizeBranch(Branch $branch, Booking $booking): void
    {
        Gate::authorize('manageBookings', $branch);
        abort_unless($booking->branch_id === $branch->id, 404);
    }

    private function page(Booking $booking, PaymentManager $manager, bool $salon)
    {
        $booking->load('branch');
        return view('payments.show', ['booking' => $booking, 'salon' => $salon,
            'payments' => Payment::with('refunds')->where('booking_id', $booking->id)->orderByDesc('id')->get(),
            'momoEnabled' => app(\App\Services\MomoGateway::class)->enabledFor($booking->branch),
            'totals' => $manager->totals($booking)]);
    }
}
