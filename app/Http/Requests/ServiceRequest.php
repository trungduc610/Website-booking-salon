<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')
                ->where('business_id', $this->route('branch')->business_id)->whereNull('deleted_at')],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'bookable' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Danh mục không thuộc doanh nghiệp của chi nhánh này.',
            'price.min' => 'Giá không được âm.',
            'price.max' => 'Giá vượt quá giới hạn cho phép.',
            'price.decimal' => 'Giá có tối đa hai chữ số thập phân.',
            'duration_minutes.min' => 'Thời lượng tối thiểu là 5 phút.',
            'duration_minutes.max' => 'Thời lượng tối đa là 600 phút.',
        ];
    }
}
