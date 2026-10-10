@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
    <div class="auth-shell">
        <aside class="story-panel" aria-label="Giới thiệu GlowBook">
            <span class="eyebrow">CHĂM SÓC BẢN THÂN, THEO CÁCH CỦA BẠN</span>
            <h1>Dành thời gian<br> cho điều khiến<br> bạn <em>rạng rỡ.</em></h1>
            <p>Một tài khoản để bắt đầu hành trình chăm sóc bản thân cùng GlowBook.</p>
            <div class="story-art" aria-hidden="true">
                <div class="arch arch-one"></div>
                <div class="arch arch-two"></div><span class="art-star">✳</span>
            </div>
            <span class="story-caption">Một khoảng nghỉ dành riêng cho bạn</span>
        </aside>
        <section class="form-panel">
            <div class="form-heading"><span class="eyebrow">CHÀO MỪNG TRỞ LẠI</span>
                <h2>Đăng nhập</h2>
                <p>Rất vui được gặp lại bạn.</p>
            </div>
            <form method="post" action="{{ route('login.store') }}" data-submit-form>
                @csrf
                <x-field name="email" label="Email" type="email" autocomplete="username" :required="true"
                    maxlength="191" autofocus />
                <x-field name="password" label="Mật khẩu" type="password" autocomplete="current-password"
                    :required="true" />
                <button class="btn btn-primary submit-button" type="submit" data-submit-button><span data-button-label>Đăng
                        nhập</span><span class="button-spinner" aria-hidden="true" hidden></span></button>
                <p class="form-status" role="status" data-submit-status></p>
            </form>
            <p class="form-switch">Bạn chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></p>
        </section>
    </div>
@endsection
