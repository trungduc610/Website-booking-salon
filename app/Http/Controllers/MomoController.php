<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\MomoGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MomoController extends Controller
{
    public function checkout(Request $request, Booking $booking, MomoGateway $gateway)
    {
        return redirect()->away($gateway->checkout($request->user(), $booking));
    }

    public function reconcile(Request $request, Booking $booking, Payment $payment, MomoGateway $gateway)
    {
        $gateway->reconcile($request->user(), $booking, $payment);
        return back()->with('success', 'Đã cập nhật kết quả đối soát MoMo. Kiểm tra trạng thái giao dịch bên dưới.');
    }

    public function ipn(Request $request, MomoGateway $gateway)
    {
        abort_unless($request->isJson(), 415);
        abort_if(strlen($request->getContent()) > 20000, 413);
        // Verify the original JSON values, before TrimStrings/ConvertEmptyStringsToNull.
        $raw = json_decode($request->getContent(), true);
        abort_unless(is_array($raw), 422);
        $rules = ['amount' => ['required','integer','min:1'], 'resultCode' => ['required','integer'],
            'transId' => ['required','integer','min:0'], 'responseTime' => ['required','integer','min:0'],
            'signature' => ['required','string','regex:/^[a-f0-9]{64}$/'], 'extraData' => ['present','string','max:1000']];
        foreach (['partnerCode','orderId','requestId','orderInfo','orderType','payType','message'] as $key) {
            $rules[$key] = ['present','string','max:1000'];
        }
        $data = Validator::make($raw, $rules)->validate();
        $gateway->notify($data);
        return response()->noContent();
    }
}
