@extends('layouts.app')
@section('title', 'Đặt lịch')
@section('content')
    <div class="booking-heading">
        <a class="back-link" href="{{ route('salons.index') }}">← Khám phá salon</a><span class="eyebrow">Đặt lịch cùng
            Glowbook</span>
        <h1>Cuộc hẹn của bạn.</h1>
        <p>{{ $branch->name }} · {{ $branch->address_line }}</p>
    </div>
    <x-errors />
    <form method="post" action="{{ route('bookings.store', $branch) }}" class="booking-layout"
        data-slots-url="{{ route('bookings.slots', $branch) }}" data-booking-wizard data-submit-form
        data-initial-step="{{ $errors->hasAny(['date', 'time', 'staff_id']) ? 1 : ($errors->hasAny(['voucher_code', 'note']) ? 2 : 0) }}"
        data-availability-url="{{ route('bookings.availability', $branch) }}">@csrf
        <input type="hidden" name="request_token"
            value="{{ old('request_token', (string) Illuminate\Support\Str::uuid()) }}">
        <div class="booking-main">
            <ol class="booking-stepper" aria-label="Tiến trình đặt lịch">
                @foreach (['Dịch vụ', 'Ngày & giờ', 'Thông tin', 'Xác nhận'] as $label)
                    <li data-step-marker="{{ $loop->index }}"><span>{{ $loop->iteration }}</span>{{ $label }}</li>
                @endforeach
            </ol>
            <p class="wizard-progress" data-step-status role="status" hidden></p>
            <section class="wizard-panel" data-step="0" aria-labelledby="services-title"><span class="eyebrow">Bước 1 ·
                    Chọn dịch vụ</span>
                <h2 id="services-title" tabindex="-1">Bạn muốn chăm sóc điều gì?</h2>
                <p class="muted">Chọn một hoặc nhiều dịch vụ để tạo cuộc hẹn của riêng bạn.</p>
                <label class="visually-hidden" for="service-search">Tìm dịch vụ</label><input class="form-control mb-4"
                    id="service-search" type="search" placeholder="Tìm dịch vụ…" data-service-search>
                <div class="category-pills" role="group" aria-label="Lọc danh mục"><button type="button"
                        class="category-pill" data-category="all" aria-pressed="true">Tất cả</button>
                    @foreach ($services->pluck('category')->filter()->unique('id') as $category)
                        <button type="button" class="category-pill" data-category="{{ $category->id }}"
                            aria-pressed="false">{{ $category->name }}</button>
                    @endforeach
                </div>
                <fieldset>
                    <legend class="visually-hidden">Dịch vụ</legend>
                    @forelse($services as $service)
                        <label class="service-choice" data-service-card
                            data-category-id="{{ $service->category_id }}"><input type="checkbox" name="service_ids[]"
                                value="{{ $service->id }}" data-price="{{ $service->price }}"
                                data-duration="{{ $service->duration_minutes }}" data-name="{{ $service->name }}"
                                @checked(in_array($service->id, old('service_ids', [])))><span
                                class="service-copy"><strong>{{ $service->name }}</strong><span>{{ $service->description }}</span><small>{{ $service->duration_minutes }}
                                    phút · {{ $service->category?->name }}</small></span><strong
                                class="service-price">{{ number_format((float) $service->price, 2, ',', '.') }}
                                ₫</strong></label>
                    @empty<div class="empty-state">
                            <h3>Dịch vụ đang được chuẩn bị</h3>
                            <p>Chi nhánh hiện chưa có dịch vụ nhận lịch.</p><a href="{{ route('salons.index') }}">Tìm chi
                                nhánh khác</a>
                        </div>
                    @endforelse
                </fieldset>
                <p hidden data-service-empty class="muted">Không tìm thấy dịch vụ phù hợp.</p>
            </section>
            <section class="wizard-panel" data-step="1" aria-labelledby="time-title"><span class="eyebrow">Bước 2 · Chọn
                    chuyên viên và thời gian</span>
                <h2 id="time-title" tabindex="-1">Khi nào bạn muốn đến?</h2>
                <p class="muted">Giờ địa phương: {{ $branch->timezone }}. Kiểm tra để biết khung giờ còn trống.</p>
                <label for="staff_id" class="form-label">Chuyên viên</label><select name="staff_id" id="staff_id"
                    class="form-control">
                    <option value="">Bất kỳ nhân viên phù hợp</option>
                    @foreach ($staffList as $staff)
                        <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>
                            {{ $staff->full_name }}{{ $staff->position ? ' · ' . $staff->position : '' }}</option>
                    @endforeach
                </select>
                <div class="staff-preview">
                    @foreach ($staffList as $staff)
                        <details class="specialist-detail">
                            <summary><span class="staff-avatar"
                                    aria-hidden="true">{{ mb_substr($staff->full_name, 0, 1) }}</span><span>{{ $staff->full_name }}<small>{{ $staff->position }}</small></span><span
                                    aria-hidden="true">+</span></summary>
                            <p>{{ $staff->bio ?: 'Chọn chuyên viên và kiểm tra thời gian phù hợp với dịch vụ của bạn.' }}
                            </p>
                        </details>
                    @endforeach
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-6"><label for="date" class="form-label">Ngày hẹn</label><input type="date"
                            id="date" name="date" class="form-control" value="{{ old('date') }}"
                            min="{{ now($branch->timezone)->toDateString() }}" required></div>
                    <div class="col-md-6"><label for="time" class="form-label">Giờ bắt đầu</label><input type="time"
                            id="time" name="time" class="form-control" value="{{ old('time') }}" required></div>
                </div>
                <div class="quick-dates" aria-label="Chọn nhanh ngày hẹn">
                    @for ($i = 0; $i < 7; $i++)
                        @php($day = now($branch->timezone)->addDays($i))
                        <button class="date-choice" type="button" data-date-choice="{{ $day->toDateString() }}"
                            aria-label="{{ $day->locale('vi')->isoFormat('dddd, D [tháng] M') }}"
                            aria-pressed="false"><small>{{ $i === 0 ? 'Hôm nay' : ($i === 1 ? 'Ngày mai' : ($day->dayOfWeek === 0 ? 'Chủ nhật' : 'Thứ ' . ($day->dayOfWeek + 1))) }}</small><strong>{{ $day->format('d/m') }}</strong></button>
                    @endfor
                </div>
                <button class="btn mt-3" type="button" data-load-slots>Xem giờ còn trống</button>
                <p class="field-hint" role="status" data-slots-status>Chọn ngày và dịch vụ để xem các khung giờ gợi ý.
                </p>
                <div class="slot-grid" data-slot-grid role="group" aria-label="Giờ hẹn gợi ý"></div><button
                    class="btn mt-3" type="button" data-check-availability>Kiểm tra khung giờ ↗</button>
                <div class="availability-result" role="status" data-availability-status>Khung giờ chỉ được giữ sau khi
                    gửi đặt lịch thành công.</div>
            </section>
            <section class="wizard-panel" data-step="2" aria-labelledby="details-title"><span class="eyebrow">Bước 3 ·
                    Thông tin của bạn</span>
                <h2 id="details-title" tabindex="-1">Lời nhắn và ưu đãi.</h2>
                <div class="customer-detail">
                    <strong>{{ auth()->user()->full_name }}</strong><span>{{ auth()->user()->email }}</span><span>{{ auth()->user()->phone }}</span>
                </div><label for="voucher_code" class="form-label">Mã ưu đãi <span class="optional">(không bắt
                        buộc)</span></label><input class="form-control" id="voucher_code" name="voucher_code"
                    maxlength="50" value="{{ old('voucher_code') }}" autocomplete="off"
                    aria-describedby="voucher-help">
                <p id="voucher-help" class="field-hint">Mã được kiểm tra và áp dụng khi gửi đặt lịch thành công. Tổng dự
                    kiến chưa bao gồm ưu đãi.</p><label for="note" class="form-label mt-4">Lời nhắn cho salon</label>
                <textarea name="note" id="note" class="form-control" rows="4" maxlength="1000"
                    placeholder="Sở thích hoặc điều bạn muốn chuyên viên lưu ý…">{{ old('note') }}</textarea>
            </section>
            <section class="wizard-panel" data-step="3" aria-labelledby="review-title"><span class="eyebrow">Bước 4 ·
                    Kiểm tra và xác nhận</span>
                <h2 id="review-title" tabindex="-1">Kiểm tra cuộc hẹn.</h2>
                <div class="review-details">
                    <p><strong>{{ $branch->name }}</strong><br>{{ $branch->address_line }}</p>
                    <p data-review-time></p>
                    <p data-review-staff></p>
                    <p data-review-voucher></p>
                </div>
                <div class="notice"><strong>Thông tin thanh toán</strong>
                    <p class="mb-0">Sau khi salon xác nhận, bạn có thể thanh toán bằng MoMo tại chi nhánh đã kích hoạt,
                        hoặc thanh toán trực tiếp. Phương thức khả dụng hiển thị trong chi tiết lịch hẹn.</p>
                </div>
                <p class="muted">Salon sẽ kiểm tra lại thời gian và ưu đãi khi bạn gửi yêu cầu.</p><button
                    class="btn btn-primary submit-button" type="submit" data-submit-button
                    @disabled($services->isEmpty())><span data-button-label>Xác nhận đặt lịch</span><x-icon
                        name="check" /><span class="button-spinner" aria-hidden="true" hidden></span></button>
                <p class="form-status" role="status" data-submit-status></p>
            </section>
            <div class="wizard-actions" hidden data-wizard-actions><button type="button" class="mobile-summary-trigger"
                    data-open-summary aria-haspopup="dialog">Cuộc hẹn <strong data-mobile-total>0 ₫</strong> <span
                        aria-hidden="true">⌃</span></button><button type="button" class="btn" data-step-back>← Quay
                    lại</button>
                <p data-wizard-error role="alert"></p><button type="button" class="btn btn-primary"
                    data-step-next>Tiếp tục <x-icon name="arrow" /></button>
            </div>
        </div>
        <aside class="booking-summary" aria-label="Tóm tắt cuộc hẹn"><span class="eyebrow">Tóm tắt cuộc hẹn</span>
            <h2>Cuộc hẹn của bạn</h2>
            <p>{{ $branch->name }}</p>
            <div class="summary-divider"></div>
            <ul data-summary-services>
                <li>Chọn dịch vụ để bắt đầu.</li>
            </ul>
            <dl>
                <div>
                    <dt>Thời lượng dịch vụ</dt>
                    <dd><span data-summary-duration>0</span> phút</dd>
                </div>
                <div>
                    <dt>Khoảng đệm lịch salon</dt>
                    <dd>{{ $bufferMinutes }} phút</dd>
                </div>
                <div>
                    <dt>Ưu đãi</dt>
                    <dd>Kiểm tra khi gửi</dd>
                </div>
                <div class="summary-total">
                    <dt>Tạm tính</dt>
                    <dd data-summary-total>0 ₫</dd>
                </div>
            </dl>
            <p class="field-hint">Giá cuối cùng và ưu đãi được xác nhận sau khi đặt lịch.</p>
            <div class="summary-promise"><span aria-hidden="true">✧</span> Một khoảng nghỉ, một phiên bản rạng rỡ hơn.
            </div>
        </aside>
    </form>
    <dialog class="summary-drawer" data-summary-dialog aria-labelledby="drawer-title">
        <div class="drawer-handle" data-drawer-handle aria-hidden="true"></div>
        <div class="flex items-center justify-between gap-4">
            <h2 id="drawer-title">Cuộc hẹn của bạn</h2><button type="button" class="icon-button" data-close-summary
                aria-label="Đóng tóm tắt">×</button>
        </div>
        <div data-drawer-content></div>
    </dialog>
@endsection
