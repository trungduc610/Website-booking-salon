<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email', 'max:191'], 'password' => ['required', 'string', 'max:4096']];
    }

    public function authenticate(): void
    {
        // Separate account+IP and IP budgets limit both targeted and rotating-account attacks.
        $key = 'login:'.hash('sha256', $this->string('email').'|'.$this->ip());
        $ipKey = 'login-ip:'.hash('sha256', (string) $this->ip());
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            throw ValidationException::withMessages(['email' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau một phút.']);
        }

        if (! Auth::attempt([...$this->only('email', 'password'), 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản không khả dụng.']);
        }

        RateLimiter::clear($key);
    }
}
