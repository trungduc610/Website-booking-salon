<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.start_time' => ['required', 'date_format:H:i'],
            'hours.*.end_time' => ['required', 'date_format:H:i', 'after:hours.*.start_time'],
            'hours.*.is_off' => ['required', 'boolean'],
        ];
    }
}
