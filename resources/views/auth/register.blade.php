@extends('layouts.app')
@section('title', 'Đăng ký')
@section('content')
    <div class="auth-shell">
        <aside class="story-panel">
            <span class="eyebrow">BẮT ĐẦU TỪ MỘT KHOẢNH KHẮC NHỎ</span>
            <h1>Chăm sóc bạn.<br> Từ hôm nay.</h1>
            <p>Tạo tài khoản cá nhân của bạn để sẵn sàng khám phá GlowBook.</p>
            <div class="story-art" aria-hidden="true">
                <div class="arch arch-one"></div>
                <div class="arch arch-two"></div><span class="art-star">✳</span>
            </div>
            <span class="story-caption">Bắt đầu chăm sóc bản thân từ hôm nay</span>
        </aside>
        <section class="form-panel">
            <div class="form-heading"><span class="eyebrow">GLOWBOOK & BẠN</span>
                <h2>Tạo tài khoản</h2>
                <p>Chỉ vài thông tin để bắt đầu.</p>
            </div>
            <form method="post" action="{{ route('register.store') }}" data-submit-form>
                @csrf
                <x-field name="full_name" label="Họ và tên" autocomplete="name" :required="true" maxlength="150"
                    autofocus />
                <x-field name="email" label="Email" type="email" autocomplete="email" :required="true"
                    maxlength="191" />
                <x-field name="phone" label="Số điện thoại" type="tel" autocomplete="tel" maxlength="16" />
                <x-field name="password" label="Mật khẩu" type="password" autocomplete="new-password" :required="true"
                    minlength="8" hint="Sử dụng ít nhất 8 ký tự." />
                <x-field name="password_confirmation" label="Nhập lại mật khẩu" type="password" autocomplete="new-password"
                    :required="true" />
                <button class="btn btn-primary submit-button" type="submit" data-submit-button><span data-button-label>Tạo
                        tài khoản</span><span class="button-spinner" aria-hidden="true" hidden></span></button>
                <p class="form-status" role="status" data-submit-status></p>
            </form>
            <p class="form-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
        </section>
    </div>
@endsection
