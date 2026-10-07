<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Services\Availability;
use App\Services\BookingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function salons()
    {
        $branches = Branch::with('business')->where('operational_status', 'ACTIVE')
            ->whereHas('business', fn ($q) => $q->where('status', 'ACTIVE'))->orderBy('name')->paginate(12);
        return view('bookings.salons', compact('branches'));
    }

    public function create(Branch $branch)
    {
        $branch->load('business');
        abort_unless($branch->operational_status === 'ACTIVE' && $branch->business?->status === 'ACTIVE', 404);
        return view('bookings.form', ['branch' => $branch,
            'services' => $branch->services()->where('status', 'ACTIVE')->where('bookable', true)->orderBy('name')->get(),
            'staffList' => $branch->staff()->where('status', 'ACTIVE')->where('is_bookable', true)->where('public_visible', true)->orderBy('full_name')->get()]);
    }

    public function availability(BookingRequest $request, Branch $branch, Availability $availability)
    {
        $plan = $availability->plan($branch, $request->validated());
        return response()->json(['available' => $plan['staff']->isNotEmpty(), 'end_time' => $plan['end']->format('H:i'),
            'message' => $plan['staff']->isNotEmpty() ? 'Còn nhân viên phù hợp. Giờ dự kiến kết thúc: '.$plan['end']->format('H:i') : 'Không còn nhân viên phù hợp.']);
    }

    public function store(BookingRequest $request, Branch $branch, BookingManager $manager)
    {
        $booking = $manager->create($request->user(), $branch, $request->validated());
        return redirect()->route('bookings.show', $booking)->with('success', 'Đã ghi nhận lịch hẹn '.$booking->booking_code.'.');
    }

    public function index(Request $request)
    {
        $profile = $request->user()->customerProfile()->firstOrFail();
        $bookings = Booking::with('branch')->where('customer_id', $profile->id)->orderByDesc('appointment_date')->paginate(15);
        return view('bookings.index', ['bookings' => $bookings, 'branch' => null]);
    }

    public function show(Request $request, Booking $booking)
    {
        $profile = $request->user()->customerProfile()->firstOrFail();
        abort_unless($booking->customer_id === $profile->id, 403);
        $booking->load(['items', 'branch']);
        return view('bookings.show', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking, BookingManager $manager)
    {
        $manager->transition($request->user(), $booking, 'CANCELLED', true);
        return back()->with('success', 'Đã hủy lịch hẹn.');
    }

    public function salon(Branch $branch)
    {
        Gate::authorize('manageBookings', $branch);
        return view('bookings.index', ['branch' => $branch,
            'bookings' => Booking::with('items')->where('branch_id', $branch->id)->orderByDesc('appointment_date')->paginate(15)]);
    }

    public function transition(Request $request, Branch $branch, Booking $booking, BookingManager $manager)
    {
        Gate::authorize('manageBookings', $branch);
        abort_unless($booking->branch_id === $branch->id, 404);
        $data = $request->validate(['status' => ['required', 'in:CONFIRMED,REJECTED,CHECKED_IN,IN_PROGRESS,COMPLETED,CANCELLED,NO_SHOW']]);
        $manager->transition($request->user(), $booking, $data['status']);
        return back()->with('success', 'Đã cập nhật trạng thái lịch hẹn.');
    }
}
