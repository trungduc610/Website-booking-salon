@extends('layouts.app')
@section('title', 'Quản trị')
@section('content')
<section class="dashboard-card"><span class="eyebrow">QUẢN TRỊ GLOWBOOK</span><h1>Không gian quản trị</h1><p>Tài khoản của bạn có quyền quản trị nền tảng đang còn hiệu lực.</p><a href="{{ route('dashboard') }}">Về tài khoản</a></section>
@endsection
