<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('service_ids')) {
            $this->merge(['service_ids' => []]);
        }
    }
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('staff_profiles')->where('branch_id', $this->route('branch')->id)->ignore($this->route('staff')?->id)],
            'is_bookable' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'LOCKED'])],
            'service_ids' => ['present', 'array', 'max:100'],
            'service_ids.*' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where('branch_id', $this->route('branch')->id)->whereNull('deleted_at')],
        ];
    }
}
