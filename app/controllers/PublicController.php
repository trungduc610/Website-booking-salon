<?php
// ============================================================
//  app/controllers/PublicController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BusinessModel.php';
require_once BASE_PATH . '/app/models/ServiceModel.php';

class PublicController extends Controller
{
    private BusinessModel $businesses;

    public function __construct()
    {
        $this->businesses = new BusinessModel();
    }

    // GET /  → Trang chủ
    public function home(): void
    {
        $featured = $this->businesses->getActive(8);

        $db = \Database::getInstance();
        $provinces = $db->query("SELECT * FROM provinces ORDER BY name");

        $this->view('public/home', [
            'pageTitle' => 'Đặt lịch làm đẹp — GlowBook',
            'featured'  => $featured,
            'provinces' => $provinces,
        ]);
    }

    // FIX (BỔ SUNG): 3 action dưới đây bị THIẾU HOÀN TOÀN trong bản gốc —
    // layout (app/views/layouts/main.php) có link footer trỏ tới
    // url('help'), url('contact'), url('privacy') trên MỌI TRANG, nhưng
    // không có route GET nào khớp 3 đường dẫn này lẫn method xử lý.
    // Kết quả: bấm vào "Trung tâm trợ giúp" / "Liên hệ" / "Chính sách bảo
    // mật" ở footer của BẤT KỲ trang nào (kể cả dashboard) đều ra 404 —
    // đây chính là lỗi "bấm tính năng khác thì hiện lỗi 404".

    // GET /help
    public function help(): void
    {
        $this->view('public/static_page', [
            'pageTitle' => 'Trung tâm trợ giúp',
            'heading'   => 'Trung tâm trợ giúp',
            'sections'  => [
                ['title' => 'Cách đặt lịch', 'body' =>
                    'Chọn salon, chọn dịch vụ và khung giờ còn trống, sau đó xác nhận đặt lịch. Bạn sẽ nhận được mã lịch hẹn ngay sau khi đặt thành công.'],
                ['title' => 'Huỷ hoặc đổi lịch hẹn', 'body' =>
                    'Vào mục "Lịch của tôi", chọn lịch hẹn cần huỷ. Một số chi nhánh có thể tính phí nếu huỷ quá sát giờ hẹn — chính sách cụ thể hiển thị ngay tại trang chi tiết lịch hẹn.'],
                ['title' => 'Tài khoản và bảo mật', 'body' =>
                    'Nếu quên mật khẩu hoặc cần hỗ trợ về tài khoản, vui lòng liên hệ qua trang Liên hệ để được trợ giúp.'],
            ],
        ]);
    }

    // GET /contact
    public function contact(): void
    {
        $this->view('public/static_page', [
            'pageTitle' => 'Liên hệ',
            'heading'   => 'Liên hệ với chúng tôi',
            'sections'  => [
                ['title' => 'Email hỗ trợ', 'body' => 'support@glowbook.vn'],
                ['title' => 'Giờ làm việc', 'body' => 'Thứ 2 – Thứ 7, 8:00 – 18:00'],
            ],
        ]);
    }

    // GET /privacy
    public function privacy(): void
    {
        $this->view('public/static_page', [
            'pageTitle' => 'Chính sách bảo mật',
            'heading'   => 'Chính sách bảo mật',
            'sections'  => [
                ['title' => 'Dữ liệu chúng tôi thu thập', 'body' =>
                    'GlowBook chỉ thu thập thông tin cần thiết để đặt lịch: họ tên, số điện thoại, và lịch sử đặt lịch của bạn.'],
                ['title' => 'Cách chúng tôi sử dụng dữ liệu', 'body' =>
                    'Thông tin của bạn chỉ được chia sẻ với salon mà bạn đặt lịch, phục vụ đúng mục đích xác nhận và thực hiện dịch vụ.'],
            ],
        ]);
    }

    // GET /search?q=...&province_id=...&page=...
    public function search(): void
    {
        $keyword    = $this->query('q');
        $provinceId = (int)$this->query('province_id', 0);
        $page       = max(1, (int)$this->query('page', 1));

        $db = \Database::getInstance();
        $provinces = $db->query("SELECT * FROM provinces ORDER BY name");

        $result = $this->businesses->search($keyword, $provinceId, $page);

        $this->view('public/search', [
            'pageTitle'   => 'Tìm kiếm salon — GlowBook',
            'result'      => $result,
            'keyword'     => $keyword,
            'provinceId'  => $provinceId,
            'provinces'   => $provinces,
        ]);
    }

    // GET /salon/{slug}  → Trang chi tiết salon
    public function salonDetail(string $slug): void
    {
        $salon = $this->businesses->findBySlug($slug);
        if (!$salon) $this->abort(404, 'Không tìm thấy salon này.');

        $db = \Database::getInstance();

        // Chi nhánh của salon
        $branches = $db->query(
            "SELECT br.*, d.name AS district_name
             FROM branches br
             LEFT JOIN districts d ON d.id = br.district_id
             WHERE br.business_id = ? AND br.operational_status = 'ACTIVE' AND br.deleted_at IS NULL",
            [$salon['id']]
        );

        // Dịch vụ (từ chi nhánh đầu tiên)
        $services = [];
        if ($branches) {
            $svcModel = new ServiceModel();
            $services = $svcModel->groupedByBranch($branches[0]['id']);
        }

        // Đánh giá gần nhất
        $reviews = $db->query(
            "SELECT r.*, u.full_name AS customer_name, u.avatar AS customer_avatar
             FROM reviews r
             JOIN bookings bk ON bk.id = r.booking_id
             JOIN branches br ON br.id = bk.branch_id
             LEFT JOIN customer_profiles cp ON cp.id = r.customer_id
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE br.business_id = ? AND r.status = 'PUBLISHED'
             ORDER BY r.created_at DESC LIMIT 6",
            [$salon['id']]
        );

        // Ảnh salon
        $photos = $db->query(
            "SELECT * FROM media_files WHERE business_id = ? AND visibility = 'PUBLIC' ORDER BY created_at DESC LIMIT 8",
            [$salon['id']]
        );

        $this->view('public/salon_detail', [
            'pageTitle' => $salon['name'] . ' — GlowBook',
            'salon'     => $salon,
            'branches'  => $branches,
            'services'  => $services,
            'reviews'   => $reviews,
            'photos'    => $photos,
        ]);
    }

    // GET /book?branch_id=...&service_ids[]=...
    public function bookingForm(): void
    {
        $branchId   = (int)$this->query('branch_id');
        $serviceIds = $_GET['service_ids'] ?? [];

        if (!$branchId) $this->abort(400, 'Chi nhánh không hợp lệ.');

        $db = \Database::getInstance();

        $branch = $db->queryOne(
            "SELECT br.*, bu.name AS business_name, bu.logo, bu.slug AS business_slug
             FROM branches br JOIN businesses bu ON bu.id = br.business_id
             WHERE br.id = ? AND br.deleted_at IS NULL LIMIT 1",
            [$branchId]
        );
        if (!$branch) $this->abort(404, 'Chi nhánh không tồn tại.');

        $svcModel = new ServiceModel();
        $allServices = $svcModel->findByBranch($branchId, true);

        // Dịch vụ đã chọn trước
        $selectedServices = [];
        foreach ($serviceIds as $sid) {
            foreach ($allServices as $s) {
                if ($s['id'] == $sid) { $selectedServices[] = $s; break; }
            }
        }

        // Staff có thể chọn
        $staffList = $db->query(
            "SELECT sp.id, sp.full_name, sp.position, sp.avatar, sp.experience_years
             FROM staff_profiles sp
             WHERE sp.branch_id = ? AND sp.is_bookable = 1 AND sp.status = 'ACTIVE' AND sp.deleted_at IS NULL",
            [$branchId]
        );

        // Giờ làm việc của chi nhánh
        $workingHours = $db->query(
            "SELECT * FROM branch_working_hours WHERE branch_id = ? ORDER BY day_of_week",
            [$branchId]
        );

        $this->view('public/booking_form', [
            'pageTitle'        => 'Đặt lịch — ' . $branch['business_name'],
            'branch'           => $branch,
            'allServices'      => $allServices,
            'selectedServices' => $selectedServices,
            'staffList'        => $staffList,
            'workingHours'     => $workingHours,
        ]);
    }

    // GET /book/success  (mã lịch hẹn đọc từ session, không còn đặt trên URL
    // để tránh lộ thông tin khách vãng lai qua Referer header / access log)
    public function bookingSuccess(): void
    {
        $code = Session::get('last_booking_code') ?: $this->query('code');
        Session::remove('last_booking_code'); // dùng 1 lần

        if (!$code) {
            $this->redirect('/');
            return;
        }

        $db = \Database::getInstance();
        $booking = $db->queryOne(
            "SELECT bk.*, br.name AS branch_name, bu.name AS business_name, bu.address_line AS business_address,
                    bc.full_name AS guest_name, bc.phone AS guest_phone
             FROM bookings bk
             JOIN branches br ON br.id = bk.branch_id
             JOIN businesses bu ON bu.id = br.business_id
             LEFT JOIN booking_contacts bc ON bc.booking_id = bk.id
             WHERE bk.booking_code = ? LIMIT 1",
            [$code]
        );

        $this->view('public/booking_success', [
            'pageTitle' => 'Đặt lịch thành công — GlowBook',
            'booking'   => $booking,
            'code'      => $code,
        ]);
    }
}
