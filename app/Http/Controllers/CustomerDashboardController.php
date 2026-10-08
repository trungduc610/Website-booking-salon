<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $customerId = $request->user()->customerProfile()->value('id');
        $upcoming = $customerId ? Booking::with('branch')->where('customer_id', $customerId)
            ->whereIn('status', Booking::ACTIVE)
            ->where('appointment_date', '>=', now()->subDay()->toDateString())
            ->where(fn ($q) => $q->where('status', '<>', 'PENDING')->orWhereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now()))
            ->orderBy('appointment_date')->orderBy('appointment_start_time')->get()
            ->first(fn ($booking) => \Carbon\CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_end_time, $booking->branch->timezone)->isFuture()) : null;
        return view('dashboard', compact('upcoming'));
    }
}
