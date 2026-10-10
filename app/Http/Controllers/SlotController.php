<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlotRequest;
use App\Models\Branch;
use App\Services\Availability;

class SlotController extends Controller
{
    public function __invoke(SlotRequest $request, Branch $branch, Availability $availability)
    {
        $branch->loadMissing('business');
        abort_unless($branch->operational_status === 'ACTIVE' && $branch->business?->status === 'ACTIVE', 404);
        return response()->json($availability->slots($branch, $request->validated()));
    }
}
