<?php
// ============================================================
//  helpers/validation.php
//  Các hàm kiểm tra & chuẩn hoá dữ liệu — bổ sung sau audit.
//  Được nạp từ public/index.php.
// ============================================================

// ---- Ngày & giờ --------------------------------------------

/** Kiểm tra chuỗi có đúng định dạng Y-m-d và là ngày có thật (chặn 2025-02-31) */
function isValidDate(string $date): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/** Chấp nhận H:i hoặc H:i:s */
function isValidTime(string $time): bool
{
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
}

/** "9:5" / "09:05" → "09:05:00" */
function normalizeTime(string $time): string
{
    $parts = array_map('intval', explode(':', $time));
    return sprintf('%02d:%02d:%02d', $parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
}

function timeToSeconds(string $time): int
{
    [$h, $m, $s] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
    return $h * 3600 + $m * 60 + $s;
}

function secondsToTime(int $seconds): string
{
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

// ---- Số điện thoại Việt Nam --------------------------------

/**
 * Chuẩn hoá SĐT Việt Nam về dạng 0xxxxxxxxx (10 số).
 * Chấp nhận: 0912345678, 84912345678, +84 912 345 678, 0912-345-678
 * Trả về null nếu không hợp lệ.
 */
function normalizeVnPhone(string $phone): ?string
{
    $digits = preg_replace('/[^\d+]/', '', $phone);
    $digits = ltrim($digits, '+');

    if (str_starts_with($digits, '84')) {
        $digits = '0' . substr($digits, 2);
    }
    if (!str_starts_with($digits, '0')) {
        $digits = '0' . $digits;
    }

    if (!preg_match('/^0(3[2-9]|5[2689]|7[06-9]|8[1-9]|9[0-46-9]|2\d)\d{7}$/', $digits)) {
        return null;
    }
    return $digits;
}

function isValidVnPhone(string $phone): bool
{
    return normalizeVnPhone($phone) !== null;
}

/** Hiển thị đẹp: 0912345678 → 0912 345 678 */
function formatVnPhone(?string $phone): string
{
    $p = $phone ? normalizeVnPhone($phone) : null;
    if (!$p) return $phone ? e($phone) : '—';
    return substr($p, 0, 4) . ' ' . substr($p, 4, 3) . ' ' . substr($p, 7);
}

// ---- Nhãn trạng thái booking ---------------------------------

/** Chữ thuần (không HTML) — dùng khi cần nhét vào flash message */
function bookingStatusText(string $status): string
{
    return match ($status) {
        'PENDING'     => 'Chờ xác nhận',
        'CONFIRMED'   => 'Đã xác nhận',
        'CHECKED_IN'  => 'Đã đến salon',
        'IN_PROGRESS' => 'Đang phục vụ',
        'COMPLETED'   => 'Hoàn thành',
        'CANCELLED'   => 'Đã huỷ',
        'NO_SHOW'     => 'Khách không đến',
        'REJECTED'    => 'Bị từ chối',
        'EXPIRED'     => 'Hết hạn giữ chỗ',
        default       => $status,
    };
}

/** Badge HTML — thay cho bookingStatusLabel() cũ (dùng inline style) */
function bookingStatusBadge(string $status): string
{
    $map = [
        'PENDING'     => ['badge-warning', '⏳'],
        'CONFIRMED'   => ['badge-info',    '✅'],
        'CHECKED_IN'  => ['badge-info',    '📍'],
        'IN_PROGRESS' => ['badge-info',    '🔄'],
        'COMPLETED'   => ['badge-success', '✔'],
        'CANCELLED'   => ['badge-danger',  '✖'],
        'NO_SHOW'     => ['badge-danger',  '🚫'],
        'REJECTED'    => ['badge-gray',    '❌'],
        'EXPIRED'     => ['badge-gray',    '⌛'],
    ];
    [$cls, $icon] = $map[$status] ?? ['badge-gray', ''];
    return '<span class="badge ' . $cls . '">' . $icon . ' ' . e(bookingStatusText($status)) . '</span>';
}
