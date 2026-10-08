<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Services\BookingRescheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RescheduleController extends Controller
{
    private function data(Request $request, bool $customer): array
    {
        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'original_start' => ['required', 'date_format:Y-m-d H:i:s'],
            'original_staff_id' => ['required', 'integer', 'min:1'],
            'staff_id' => [$customer ? 'nullable' : 'required', 'integer', 'min:1'],
        ]);
    }

    public function customer(Request $request, Booking $booking, BookingRescheduler $rescheduler)
    {
        abort_unless($booking->customer_id === $request->user()->customerProfile()->value('id'), 403);
        $rescheduler->move($request->user(), $booking, $this->data($request, true), true);
        return back()->with('success', 'Đã đổi lịch hẹn. Giá và ưu đãi của bạn được giữ nguyên.');
    }

    public function salon(Request $request, Branch $branch, Booking $booking, BookingRescheduler $rescheduler)
    {
        Gate::authorize('manageBookings', $branch);
        abort_unless($booking->branch_id === $branch->id, 404);
        $rescheduler->move($request->user(), $booking, $this->data($request, false), false);
        return back()->with('success', 'Đã cập nhật thời gian và chuyên viên của lịch hẹn.');
    }
}
