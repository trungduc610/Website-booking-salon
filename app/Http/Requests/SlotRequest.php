<?php

namespace App\Http\Requests;

class SlotRequest extends BookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['time'], $rules['request_token'], $rules['voucher_code'], $rules['note']);
        return $rules;
    }
}
