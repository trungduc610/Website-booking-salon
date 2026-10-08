<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlotRequest;
use App\Models\Branch;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    public function __invoke(SlotRequest $request, Branch $branch, Availability $availability)
    {
        $branch->loadMissing('business');
        abort_unless($branch->operational_status === 'ACTIVE' && $branch->business?->status === 'ACTIVE', 404);
        $data = $request->validated();
        $day = CarbonImmutable::parse($data['date'], $branch->timezone);
        $hours = DB::table('branch_working_hours')->where('branch_id', $branch->id)->where('day_of_week', $day->dayOfWeek)->first();
        if (!$hours || $hours->is_closed || DB::table('branch_holidays')->where('branch_id', $branch->id)->where('date', $data['date'])->where('is_closed', true)->exists()) {
            return response()->json(['slots' => [], 'message' => 'Salon không mở cửa vào ngày này.']);
        }
        $cursor = CarbonImmutable::parse($data['date'].' '.$hours->open_time, $branch->timezone);
        $close = CarbonImmutable::parse($data['date'].' '.$hours->close_time, $branch->timezone);
        $slots = [];
        // At most 48 half-hour choices. The booking transaction rechecks every selection.
        for ($i = 0; $cursor->lt($close) && $i < 48; $i++, $cursor = $cursor->addMinutes(30)) {
            $time = $cursor->format('H:i');
            try {
                $plan = $availability->plan($branch, [...$data, 'time' => $time]);
                $available = $plan['staff']->isNotEmpty();
                $slots[] = ['time' => $time, 'available' => $available, 'message' => $available ? 'Còn chỗ' : 'Không còn chuyên viên phù hợp'];
            } catch (ValidationException $e) {
                $slots[] = ['time' => $time, 'available' => false, 'message' => collect($e->errors())->flatten()->first()];
            }
        }
        return response()->json(['slots' => $slots, 'message' => 'Khung giờ được cập nhật khi kiểm tra; chưa giữ chỗ.']);
    }
}
