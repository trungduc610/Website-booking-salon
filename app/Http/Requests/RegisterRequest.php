<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\\+?[0-9]{9,15}$/', 'unique:users,phone'],
            'password' => ['bail', 'required', 'string', 'max:72', 'confirmed', Password::min(8), function ($attribute, $value, $fail): void {
                if (strlen($value) > 72) {
                    $fail('Mật khẩu quá dài. Vui lòng dùng mật khẩu ngắn hơn.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.unique' => 'Số điện thoại này đã được sử dụng.',
            'phone.regex' => 'Số điện thoại gồm 9–15 chữ số, có thể bắt đầu bằng +.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được dài hơn 72 ký tự.',
        ];
    }
}
