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
    public function salons(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $branches = Branch::with('business')->where('operational_status', 'ACTIVE')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%')->orWhere('address_line', 'like', '%'.$request->input('q').'%')->orWhereHas('business', fn ($b) => $b->where('name', 'like', '%'.$request->input('q').'%'))))
            ->whereHas('business', fn ($q) => $q->where('status', 'ACTIVE'))->orderBy('name')->paginate(12)->withQueryString();
        return view('bookings.salons', compact('branches'));
    }

    public function create(Branch $branch)
    {
        $branch->load('business');
        abort_unless($branch->operational_status === 'ACTIVE' && $branch->business?->status === 'ACTIVE', 404);
        return view('bookings.form', ['branch' => $branch,
            'bufferMinutes' => (int) \Illuminate\Support\Facades\DB::table('branch_booking_policies')->where('branch_id', $branch->id)->value('default_buffer_minutes'),
            'services' => $branch->services()->with('category')->where('status', 'ACTIVE')->where('bookable', true)->orderBy('name')->get(),
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

    public function salon(Request $request, Branch $branch)
    {
        Gate::authorize('manageBookings', $branch);
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', 'in:PENDING,CONFIRMED,CHECKED_IN,IN_PROGRESS,COMPLETED,CANCELLED,NO_SHOW,REJECTED,EXPIRED'], 'booking_id' => ['nullable', 'integer', 'min:1']]);
        $today = now($branch->timezone)->toDateString();
        $base = Booking::where('branch_id', $branch->id);
        $metrics = [
            'date' => $today,
            'today' => (clone $base)->where('appointment_date', $today)->count(),
            'pending' => (clone $base)->where('status', 'PENDING')->where(fn ($q) => $q->whereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now()))->count(),
            'completed' => (clone $base)->where('appointment_date', $today)->where('status', 'COMPLETED')->count(),
        ];
        $dayStart = now($branch->timezone)->startOfDay()->setTimezone(config('app.timezone'));
        $metrics['received'] = \Illuminate\Support\Facades\DB::table('payments as p')->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->where('b.branch_id', $branch->id)->whereIn('p.status', ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'])
            ->where('p.paid_at', '>=', $dayStart)->where('p.paid_at', '<', $dayStart->copy()->addDay())->sum('p.amount');
        $noShows = (clone $base)->where('appointment_date', $today)->where('status', 'NO_SHOW')->count();
        $finished = $noShows + $metrics['completed'];
        $metrics['noShowRate'] = $finished ? round($noShows / $finished * 100, 1) : null;
        $daily = (clone $base)->whereBetween('appointment_date', [now($branch->timezone)->subDays(6)->toDateString(), $today])
            ->selectRaw('appointment_date, count(*) as total')->groupBy('appointment_date')->pluck('total', 'appointment_date');
        $metrics['trend'] = collect(range(6, 0))->map(fn ($days) => (int) ($daily[now($branch->timezone)->subDays($days)->toDateString()] ?? 0))->all();
        $scheduleDate = $request->input('date') ?: $today;
        $schedule = (clone $base)->with('items')->where('appointment_date', $scheduleDate)->whereIn('status', Booking::ACTIVE)->where(fn ($q) => $q->where('status', '<>', 'PENDING')->orWhereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now()))->orderBy('appointment_start_time')->get();
        $scheduleStaff = $branch->staff()->orderBy('full_name')->get();
        $bufferMinutes = (int) \Illuminate\Support\Facades\DB::table('branch_booking_policies')->where('branch_id', $branch->id)->value('default_buffer_minutes');
        return view('bookings.index', ['branch' => $branch, 'metrics' => $metrics, 'schedule' => $schedule, 'scheduleDate' => $scheduleDate, 'scheduleStaff' => $scheduleStaff, 'bufferMinutes' => $bufferMinutes,
            'bookings' => $base->with('items')
                ->when($request->filled('booking_id'), fn ($q) => $q->whereKey($request->integer('booking_id')))
                ->when($request->filled('date'), fn ($q) => $q->where('appointment_date', $request->input('date')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->orderByDesc('appointment_date')->orderBy('appointment_start_time')->paginate(15)->withQueryString()]);
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
