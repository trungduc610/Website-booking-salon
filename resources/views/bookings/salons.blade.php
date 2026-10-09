@extends('layouts.app')
@section('title', 'Khám phá không gian làm đẹp')
@section('content')
    <section class="editorial-hero">
        <div class="hero-copy"><span class="eyebrow">A LITTLE TIME, JUST FOR YOU</span>
            <h1>Dành một chút<br>thời gian cho <em>mình.</em></h1>
            <p>Khám phá không gian làm đẹp, tìm người chăm sóc phù hợp và chọn một cuộc hẹn theo nhịp sống của bạn.</p><a
                class="btn btn-primary" href="#discover">Tìm không gian của bạn <span aria-hidden="true">↗</span></a>
            <div class="hero-footnote"><span aria-hidden="true">✧</span> Chọn dịch vụ. Chọn thời gian. Tận hưởng.</div>
        </div>
        <div class="hero-art" role="img" aria-label="Minh họa không gian spa với vòm kiến trúc, ánh nắng và bình gốm">
            <div class="sun-orbit"></div>
            <div class="spa-arch">
                <div class="spa-shadow"></div>
                <div class="vase vase-tall"></div>
                <div class="vase vase-small"></div>
                <div class="spa-stone"></div>
            </div><span class="art-label">THE ART OF<br>FEELING GOOD.</span><span class="art-seal">CHẬM LẠI<br>✦<br>RẠNG RỠ
                HƠN</span>
        </div>
    </section>
    <div class="values-strip"><span>01 <strong>Không gian phù hợp</strong></span><span>02 <strong>Dịch vụ rõ
                ràng</strong></span><span>03 <strong>Đặt lịch dễ dàng</strong></span></div>
    <section id="discover" class="discovery-section">
        <div class="section-heading">
            <div><span class="eyebrow">YOUR NEXT MOMENT OF CARE</span>
                <h2>Tìm nơi bạn thuộc về.</h2>
            </div><span class="muted">{{ $branches->total() }} không gian đang đón bạn</span>
        </div>
        <form method="get" class="search-bar"><label for="branch-search" class="visually-hidden">Tìm tên salon hoặc địa
                chỉ</label><span aria-hidden="true">⌕</span><input id="branch-search" name="q"
                value="{{ request('q') }}" maxlength="120" placeholder="Tìm salon, chi nhánh hoặc địa chỉ…"><button
                class="btn btn-primary">Tìm không gian</button></form>
        <div class="branch-grid">
            @forelse($branches as $branch)
                <article class="branch-card">
                    <div class="branch-art branch-art-{{ $loop->index % 3 }}" aria-hidden="true">
                        <div class="mini-arch"></div>
                        <span>{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <p>{{ $branch->business->name }}</p>
                    </div>
                    <div class="branch-body">
                        <div class="card-kicker">SALON & WELLNESS <span class="status-badge status-CONFIRMED">Đang nhận
                                lịch</span></div>
                        <h3>{{ $branch->name }}</h3>
                        <p class="muted">{{ $branch->address_line }}</p><a href="{{ route('bookings.create', $branch) }}"
                            class="card-link">Khám phá dịch vụ <span aria-hidden="true">↗</span></a>
                    </div>
                </article>
            @empty<div class="empty-state"><span aria-hidden="true">✧</span>
                    <h3>Chưa tìm thấy không gian phù hợp</h3>
                    <p>Thử tên hoặc địa chỉ khác để tiếp tục khám phá.</p><a class="btn"
                        href="{{ route('salons.index') }}">Xem tất cả salon</a>
                </div>
            @endforelse
        </div>
        <div class="mt-6">{{ $branches->links() }}</div>
    </section>
    <section class="care-banner"><span aria-hidden="true">✳</span>
        <div><span class="eyebrow">MAKE ROOM FOR YOURSELF</span>
            <h2>Một cuộc hẹn nhỏ.<br>Một ngày khác biệt.</h2>
        </div><a class="btn" href="{{ route('bookings.index') }}">Lịch hẹn của bạn ↗</a>
    </section>
@endsection
