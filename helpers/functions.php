<?php
// ============================================================
//  helpers/functions.php
//  Các hàm tiện ích dùng chung toàn ứng dụng
// ============================================================

// ---- Bảo mật -----------------------------------------------

/**
 * Hash mật khẩu bằng bcrypt
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Kiểm tra mật khẩu
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Tạo chuỗi token ngẫu nhiên (dùng cho email verify, reset password...)
 */
function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Escape HTML để chống XSS khi in ra view
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- URL & Redirect -----------------------------------------

/**
 * Tạo URL tuyệt đối bắt đầu bằng / từ đường dẫn tương đối
 */
function url(string $path = ''): string
{
    $clean = ltrim($path, '/');
    return $clean === '' ? '/' : '/' . $clean;
}

/**
 * Tạo URL ảnh upload tuyệt đối bắt đầu bằng /
 */
function uploadUrl(?string $filename = null): string
{
    if ($filename === null || $filename === '') {
        return '';
    }
    if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
        return $filename;
    }
    if (str_starts_with($filename, '/uploads/')) {
        return $filename;
    }
    if (str_starts_with($filename, 'uploads/')) {
        return '/' . $filename;
    }
    return '/uploads/' . ltrim($filename, '/');
}

// ---- Format dữ liệu -----------------------------------------

/**
 * Định dạng tiền VND
 * VD: formatMoney(150000) → "150.000 ₫"
 */
function formatMoney(float|int|string $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

/**
 * Định dạng ngày giờ tiếng Việt
 * VD: formatDate('2025-08-24 14:30:00') → "24/08/2025 14:30"
 */
function formatDate(string $datetime, string $format = 'd/m/Y H:i'): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '—';
    return date($format, strtotime($datetime));
}

/**
 * Định dạng chỉ ngày
 */
function formatDateOnly(string $date): string
{
    return formatDate($date, 'd/m/Y');
}

/**
 * Định dạng thời lượng phút → "1 giờ 30 phút"
 */
function formatDuration(int $minutes): string
{
    if ($minutes < 60) return "{$minutes} phút";
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h} giờ {$m} phút" : "{$h} giờ";
}

/**
 * Rút ngắn chuỗi dài
 */
function truncate(string $text, int $maxLength = 100, string $suffix = '…'): string
{
    // FIX: chỉ định rõ 'UTF-8' để không cắt giữa ký tự nhiều byte (dấu tiếng Việt)
    if (mb_strlen($text, 'UTF-8') <= $maxLength) return $text;
    return rtrim(mb_substr($text, 0, $maxLength, 'UTF-8')) . $suffix;
}

/**
 * Tạo slug từ chuỗi tiếng Việt
 * VD: "Nail nghệ thuật" → "nail-nghe-thuat"
 */
function slugify(string $text): string
{
    // Bảng chuyển đổi ký tự có dấu
    $map = [
        'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
        'ă'=>'a','ắ'=>'a','ặ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'â'=>'a','ấ'=>'a','ậ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'đ'=>'d',
        'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
        'ê'=>'e','ế'=>'e','ệ'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
        'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
        'ô'=>'o','ố'=>'o','ộ'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ớ'=>'o','ợ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
        'ư'=>'u','ứ'=>'u','ự'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
    ];
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, $map);
    // FIX: thêm cờ /u — bản cũ thiếu cờ này nên làm việc trên byte thay vì
    // ký tự, cắt vụn các ký tự UTF-8 nhiều byte còn sót lại (VD: tên salon
    // có chữ Hán/Nhật/Hàn) và có thể sinh chuỗi UTF-8 hỏng.
    $text = preg_replace('/[^a-z0-9\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/u', '-', trim($text));
    $text = trim($text, '-');
    // FIX: không bao giờ trả về slug rỗng (VD: tên salon toàn ký tự đặc biệt)
    return $text !== '' ? $text : 'salon-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

// ---- Upload ảnh ---------------------------------------------

/**
 * Upload 1 file ảnh, trả về tên file đã lưu hoặc null nếu lỗi
 *
 * $fileKey   : key trong $_FILES
 * $subfolder : thư mục con trong uploads/ (vd: 'salons', 'avatars')
 */
function uploadImage(string $fileKey, string $subfolder = 'misc'): ?string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$fileKey];

    // Kiểm tra kích thước
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return null;
    }

    // Kiểm tra MIME type thực (không tin vào extension)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return null;
    }

    // Extension an toàn
    $ext = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    $dir = BASE_PATH . '/public/uploads/' . $subfolder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid($subfolder . '_', true) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $subfolder . '/' . $filename;
}

// ---- Flash message hiển thị ---------------------------------

/**
 * In flash message ra HTML (gọi trong view/layout)
 */
function showFlash(): void
{
    // FIX: dùng class .alert (đã có sẵn trong style.css) thay vì inline style,
    // và Controller không còn gửi thẻ HTML vào flash message nên e() không
    // còn làm hiện chữ "<strong>" ra màn hình như bản cũ.
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = Session::getFlash($type);
        if ($msg !== null && $msg !== '') {
            echo '<div class="alert alert-' . $type . '" role="alert">' . e($msg) . '</div>';
        }
    }
}

// ---- Phân trang HTML ----------------------------------------

/**
 * Render HTML phân trang
 *
 * $pager : mảng từ Model::paginate() → ['page', 'pages', 'total']
 * $url   : URL cơ sở (sẽ thêm ?page=N)
 */
function renderPagination(array $pager, string $url): string
{
    if ($pager['pages'] <= 1) return '';

    $separator = str_contains($url, '?') ? '&' : '?';

    $html  = '<nav style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap">';
    for ($i = 1; $i <= $pager['pages']; $i++) {
        $active = ($i === (int)$pager['page']) ? 'font-weight:bold;background:#3182ce;color:#fff' : 'background:#edf2f7';
        $html  .= "<a href='{$url}{$separator}page={$i}' style='padding:6px 12px;border-radius:4px;{$active};text-decoration:none'>{$i}</a>";
    }
    $html .= '</nav>';
    $html .= "<p style='color:#888;font-size:13px'>Tổng: {$pager['total']} kết quả</p>";
    return $html;
}

// ---- Trạng thái booking (nhãn tiếng Việt) -------------------

function bookingStatusLabel(string $status): string
{
    return match($status) {
        'PENDING'   => '<span style="color:#d69e2e">⏳ Chờ xác nhận</span>',
        'CONFIRMED' => '<span style="color:#3182ce">✅ Đã xác nhận</span>',
        'CHECKED_IN'=> '<span style="color:#805ad5">📍 Đã check-in</span>',
        'IN_PROGRESS'=> '<span style="color:#319795">🔄 Đang thực hiện</span>',
        'COMPLETED' => '<span style="color:#38a169">✔ Hoàn thành</span>',
        'CANCELLED' => '<span style="color:#e53e3e">✖ Đã hủy</span>',
        'NO_SHOW'   => '<span style="color:#e53e3e">🚫 Không đến</span>',
        'REJECTED'  => '<span style="color:#718096">❌ Từ chối</span>',
        default     => "<span>{$status}</span>",
    };
}

function businessStatusLabel(string $status): string
{
    return match($status) {
        'PENDING'        => '<span style="color:#d69e2e">Chờ xét duyệt</span>',
        'APPROVED','ACTIVE' => '<span style="color:#38a169">Đã duyệt</span>',
        'SUSPENDED'      => '<span style="color:#e53e3e">Đã tạm khóa</span>',
        'REJECTED'       => '<span style="color:#718096">Từ chối</span>',
        default          => "<span>{$status}</span>",
    };
}

// ---- Sao (rating) -------------------------------------------

/**
 * Hiển thị sao đánh giá
 */
function renderStars(int $rating, int $max = 5): string
{
    $html = '';
    for ($i = 1; $i <= $max; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    return "<span style='color:#f6ad55;font-size:18px'>{$html}</span>";
}
