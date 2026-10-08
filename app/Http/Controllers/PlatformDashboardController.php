<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;

class PlatformDashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', ['metrics' => [
            'branches' => Branch::where('operational_status', 'ACTIVE')->count(),
            'bookings' => Booking::whereIn('status', Booking::ACTIVE)
                ->where(fn ($q) => $q->where('status', '<>', 'PENDING')->orWhereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now()))->count(),
            'users' => User::where('is_active', true)->count(),
        ]]);
    }
}
