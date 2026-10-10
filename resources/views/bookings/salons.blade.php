@extends('layouts.app')
@section('title', 'Khám phá không gian làm đẹp')
@section('content')
    <section class="editorial-hero">
        <div class="hero-copy"><span class="eyebrow">Chăm sóc bản thân cùng Glowbook</span>
            <h1>Dành thời gian<br>cho chính mình.</h1>
            <p>Tìm salon phù hợp, chọn dịch vụ yêu thích và dành một khoảng nghỉ cho bạn.</p>
            <form method="get" action="{{ route('salons.index') }}#discover" class="discovery-search" role="search">
                <label for="branch-search" class="form-label">Bạn muốn làm đẹp ở đâu?</label>
                <div class="search-bar"><x-icon name="search" /><input id="branch-search" name="q" type="search"
                        value="{{ request('q') }}" maxlength="120" placeholder="Tên salon hoặc địa chỉ"
                        @error('q') aria-invalid="true" aria-describedby="branch-search-error" @enderror><button
                        class="btn btn-primary">Tìm salon <x-icon name="arrow" /></button></div>
                @error('q')
                    <p id="branch-search-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </form>
            <div class="hero-footnote"><x-icon name="calendar" /> Chọn dịch vụ. Chọn thời gian. Đặt lịch.</div>
        </div>
        <div class="hero-art" role="img" aria-label="Minh họa không gian spa với vòm kiến trúc, ánh nắng và bình gốm">
            <div class="sun-orbit"></div>
            <div class="spa-arch">
                <div class="spa-shadow"></div>
                <div class="vase vase-tall"></div>
                <div class="vase vase-small"></div>
                <div class="spa-stone"></div>
            </div><span class="art-label">Một khoảng nghỉ.<br>Một ngày rạng rỡ.</span><span class="art-seal">CHẬM
                LẠI<br>✦<br>RẠNG RỠ
                HƠN</span>
        </div>
    </section>
    <ol class="values-strip" aria-label="Ba bước đặt lịch">
        <li><span>1</span> Tìm salon</li>
        <li><span>2</span> Chọn dịch vụ và giờ</li>
        <li><span>3</span> Xác nhận cuộc hẹn</li>
    </ol>
    <section id="discover" class="discovery-section">
        <div class="section-heading">
            <div><span class="eyebrow">Khám phá không gian làm đẹp</span>
                <h2>Chọn nơi chăm sóc bạn.</h2>
            </div><span class="result-count" role="status">{{ $branches->total() }} salon{{ request()->filled('q') ? ' phù hợp' : ' đang nhận lịch' }}</span>
        </div>
        @if (request()->filled('q'))
            <div class="search-context"><span>Kết quả cho <strong>“{{ request('q') }}”</strong></span><a class="btn btn-sm"
                    href="{{ route('salons.index') }}#discover">Xóa tìm kiếm</a></div>
        @endif
        <div class="branch-grid">
            @forelse($branches as $branch)
                <article class="branch-card">
                    <div class="branch-art branch-art-{{ $loop->index % 3 }}" aria-hidden="true">
                        <div class="mini-arch"></div>
                        <span>{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <p>{{ $branch->business->name }}</p>
                    </div>
                    <div class="branch-body">
                        <div class="card-kicker"><span>{{ $branch->business->name }}</span> <span
                                class="status-badge status-CONFIRMED"><x-icon name="check" /> Đang nhận
                                lịch</span></div>
                        <h3>{{ $branch->name }}</h3>
                        <p class="branch-address"><x-icon name="location" />
                            <span>{{ $branch->address_line ?: 'Liên hệ salon để biết địa chỉ.' }}</span></p><a
                            href="{{ route('bookings.create', $branch) }}" class="card-link">Xem dịch vụ và đặt lịch
                            <x-icon name="arrow" /></a>
                    </div>
                </article>
            @empty<div class="empty-state"><span aria-hidden="true">✧</span>
                    @if (request()->filled('q'))
                        <h3>Chưa tìm thấy không gian phù hợp</h3>
                        <p>Thử tên hoặc địa chỉ khác để tiếp tục khám phá.</p><a class="btn"
                            href="{{ route('salons.index') }}">Xem tất cả salon</a>
                    @else
                        <h3>Chưa có salon đang nhận lịch</h3>
                        <p>Các chi nhánh sẽ xuất hiện sau khi được cấu hình và xuất bản.</p>
                        @env('local')
                            <p>Để thử đặt lịch với dữ liệu mẫu trên máy local, chạy
                                <code>php artisan db:seed --class=DemoSalonSeeder</code> rồi tải lại trang.
                            </p>
                        @endenv
                    @endif
                </div>
            @endforelse
        </div>
        <div class="mt-6">{{ $branches->links() }}</div>
    </section>
    <section class="care-banner"><span aria-hidden="true">✳</span>
        <div><span class="eyebrow">Dành một khoảng nghỉ cho bạn</span>
            <h2>Một cuộc hẹn nhỏ.<br>Một ngày khác biệt.</h2>
        </div><a class="btn" href="{{ route('bookings.index') }}">Lịch hẹn của bạn ↗</a>
    </section>
@endsection
