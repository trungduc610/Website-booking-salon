<?php
// ============================================================
//  app/controllers/SalonController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BookingModel.php';
require_once BASE_PATH . '/app/models/BusinessModel.php';
require_once BASE_PATH . '/app/models/ServiceModel.php';

class SalonController extends Controller
{
    private BookingModel  $bookings;
    private BusinessModel $businesses;

    public function __construct()
    {
        $this->bookings   = new BookingModel();
        $this->businesses = new BusinessModel();
    }

    // GET /salon/dashboard
    public function dashboard(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER','RECEPTIONIST','STAFF']);
        [$business, $branch] = $this->getCurrentBranchContext();

        $stats    = $this->bookings->statsForBranch($branch['id']);
        $db       = \Database::getInstance();
        $today    = date('Y-m-d');

        $todayBookings = $db->query(
            "SELECT bk.*, u.full_name AS customer_name, bc.full_name AS guest_name
             FROM bookings bk
             LEFT JOIN customer_profiles cp ON cp.id = bk.customer_id
             LEFT JOIN users u  ON u.id  = cp.user_id
             LEFT JOIN booking_contacts bc ON bc.booking_id = bk.id
             WHERE bk.branch_id = ? AND bk.appointment_date = ? AND bk.deleted_at IS NULL
             ORDER BY bk.appointment_start_time",
            [$branch['id'], $today]
        );

        $this->view('salon/dashboard', [
            'pageTitle'     => 'Dashboard — ' . $business['name'],
            'business'      => $business,
            'branch'        => $branch,
            'stats'         => $stats,
            'todayBookings' => $todayBookings,
        ]);
    }

    // GET /salon/bookings
    public function bookings(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER','RECEPTIONIST','STAFF']);
        [$business, $branch] = $this->getCurrentBranchContext();

        $page   = max(1, (int)$this->query('page', 1));
        $status = $this->query('status');
        $date   = $this->query('date');

        $result = $this->bookings->findByBranch($branch['id'], $page, 20, $status, $date);

        $this->view('salon/bookings', [
            'pageTitle' => 'Quản lý lịch hẹn',
            'business'  => $business,
            'branch'    => $branch,
            'result'    => $result,
            'status'    => $status,
            'date'      => $date,
        ]);
    }

    // GET /salon/services
    public function services(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $svcModel = new ServiceModel();
        $services = $svcModel->findByBranch($branch['id']);
        $categories = $svcModel->categoriesByBusiness($business['id']);

        $this->view('salon/services', [
            'pageTitle'  => 'Quản lý dịch vụ',
            'business'   => $business,
            'branch'     => $branch,
            'services'   => $services,
            'categories' => $categories,
        ]);
    }

    // GET /salon/staff
    public function staff(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $db    = \Database::getInstance();
        $staff = $db->query(
            "SELECT sp.*, u.email FROM staff_profiles sp
             LEFT JOIN users u ON u.id = sp.user_id
             WHERE sp.branch_id = ? AND sp.deleted_at IS NULL
             ORDER BY sp.full_name",
            [$branch['id']]
        );
        $this->view('salon/staff', [
            'pageTitle' => 'Quản lý nhân viên',
            'business'  => $business,
            'branch'    => $branch,
            'staff'     => $staff,
        ]);
    }

    // GET /salon/promotions
    public function promotions(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $db = \Database::getInstance();
        $vouchers = $db->query(
            "SELECT * FROM vouchers WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$business['id']]
        );
        $this->view('salon/promotions', [
            'pageTitle' => 'Khuyến mãi & Voucher',
            'business'  => $business,
            'branch'    => $branch,
            'vouchers'  => $vouchers,
        ]);
    }

    // GET /salon/reviews
    public function reviews(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $db = \Database::getInstance();
        $reviews = $db->query(
            "SELECT r.*, u.full_name AS customer_name
             FROM reviews r
             JOIN bookings bk ON bk.id = r.booking_id
             LEFT JOIN customer_profiles cp ON cp.id = r.customer_id
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE bk.branch_id = ? AND r.deleted_at IS NULL
             ORDER BY r.created_at DESC LIMIT 50",
            [$branch['id']]
        );
        $this->view('salon/reviews', [
            'pageTitle' => 'Đánh giá khách hàng',
            'business'  => $business,
            'branch'    => $branch,
            'reviews'   => $reviews,
        ]);
    }

    // GET /salon/attendance
    public function attendance(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER','RECEPTIONIST']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $date = $this->query('date', date('Y-m-d'));
        $db   = \Database::getInstance();
        $records = $db->query(
            "SELECT sa.*, sp.full_name AS staff_name, sp.position
             FROM staff_attendances sa
             JOIN staff_profiles sp ON sp.id = sa.staff_id
             WHERE sa.branch_id = ? AND sa.work_date = ?
             ORDER BY sa.scheduled_start_time",
            [$branch['id'], $date]
        );
        $this->view('salon/attendance', [
            'pageTitle' => 'Chấm công nhân viên',
            'business'  => $business,
            'branch'    => $branch,
            'records'   => $records,
            'date'      => $date,
        ]);
    }

    // GET /salon/profile
    public function profile(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        [$business, $branch] = $this->getCurrentBranchContext();
        $db = \Database::getInstance();
        $workingHours = $db->query(
            "SELECT * FROM branch_working_hours WHERE branch_id = ? ORDER BY day_of_week",
            [$branch['id']]
        );
        $this->view('salon/profile', [
            'pageTitle'    => 'Thông tin salon',
            'business'     => $business,
            'branch'       => $branch,
            'workingHours' => $workingHours,
        ]);
    }

    // POST /salon/profile/update
    public function updateProfile(): void
    {
        $this->requireRole(['BUSINESS_OWNER','BRANCH_MANAGER']);
        $this->verifyCsrf();
        [$business, $branch] = $this->getCurrentBranchContext();
        $db = \Database::getInstance();

        // Cập nhật thông tin business
        $db->execute(
            "UPDATE businesses SET name = ?, description = ?, contact_email = ?, contact_phone = ?, updated_at = NOW() WHERE id = ?",
            [$this->input('name'), $this->input('description'), $this->rawInput('contact_email'), $this->rawInput('contact_phone'), $business['id']]
        );

        // Upload logo
        $logo = uploadImage('logo', 'logos');
        if ($logo) {
            $db->execute("UPDATE businesses SET logo = ? WHERE id = ?", [$logo, $business['id']]);
        }

        $this->flash('success', 'Đã cập nhật thông tin salon.');
        $this->redirect('/salon/profile');
    }

    // GET /salon/onboarding
    public function onboarding(): void
    {
        $this->requireRole(['BUSINESS_OWNER']);
        $db   = \Database::getInstance();
        $user = \Database::getInstance()->queryOne(
            "SELECT op.* FROM business_owner_profiles op WHERE op.user_id = ? LIMIT 1",
            [Session::userId()]
        );
        $this->view('salon/onboarding', [
            'pageTitle' => 'Đăng ký salon mới',
            'profile'   => $user,
        ]);
    }

    // ---- Private helper ------------------------------------
    private function getCurrentBranchContext(): array
    {
        $db = \Database::getInstance();

        // Lấy business của user hiện tại
        $business = $db->queryOne(
            "SELECT b.* FROM businesses b
             JOIN business_owner_profiles op ON op.id = b.owner_id
             WHERE op.user_id = ? AND b.deleted_at IS NULL
             ORDER BY b.created_at DESC LIMIT 1",
            [Session::userId()]
        );

        // Nếu không có (BRANCH_MANAGER / STAFF) → lấy qua salon_members
        if (!$business) {
            $member = $db->queryOne(
                "SELECT sm.*, b.* FROM salon_members sm
                 JOIN businesses b ON b.id = sm.business_id
                 WHERE sm.user_id = ? AND sm.is_active = 1 AND sm.deleted_at IS NULL LIMIT 1",
                [Session::userId()]
            );
            if (!$member) $this->abort(403, 'Bạn chưa thuộc salon nào.');
            $business = $member;
        }

        // Lấy chi nhánh đầu tiên (hoặc session branch_id nếu có)
        $branchId = Session::get('current_branch_id');
        $branch   = $branchId
            ? $db->queryOne("SELECT * FROM branches WHERE id = ? AND business_id = ? LIMIT 1", [$branchId, $business['id']])
            : null;

        if (!$branch) {
            $branch = $db->queryOne(
                "SELECT * FROM branches WHERE business_id = ? AND deleted_at IS NULL ORDER BY created_at LIMIT 1",
                [$business['id']]
            );
        }

        if (!$branch) $this->abort(404, 'Chưa có chi nhánh nào. Hãy tạo chi nhánh trước.');
        return [$business, $branch];
    }
}
