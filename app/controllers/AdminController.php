<?php
// ============================================================
//  app/controllers/AdminController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/UserModel.php';
require_once BASE_PATH . '/app/models/BusinessModel.php';
require_once BASE_PATH . '/app/models/BookingModel.php';

class AdminController extends Controller
{
    private UserModel     $users;
    private BusinessModel $businesses;
    private BookingModel  $bookings;

    public function __construct()
    {
        $this->users      = new UserModel();
        $this->businesses = new BusinessModel();
        $this->bookings   = new BookingModel();
    }

    // GET /admin/dashboard
    public function dashboard(): void
    {
        $this->requireRole(['PLATFORM_ADMIN','COMPLIANCE','SUPPORT']);
        $db = \Database::getInstance();

        $stats = [
            'total_users'      => $this->users->count(),
            'total_salons'     => $this->businesses->count(),
            'active_salons'    => $this->businesses->count("status = 'ACTIVE'"),
            'pending_salons'   => $this->businesses->count("status IN ('PENDING','PENDING_REVIEW')"),
            'total_bookings'   => $this->bookings->count(),
            'today_bookings'   => $this->bookings->count("appointment_date = '" . date('Y-m-d') . "'"),
        ];

        $pendingSalons = $db->query(
            "SELECT b.*, u.full_name AS owner_name, u.email AS owner_email
             FROM businesses b
             JOIN business_owner_profiles op ON op.id = b.owner_id
             JOIN users u ON u.id = op.user_id
             WHERE b.status IN ('PENDING','PENDING_REVIEW') AND b.deleted_at IS NULL
             ORDER BY b.submitted_at ASC LIMIT 10"
        );

        $recentUsers = $db->query(
            "SELECT u.*, r.code AS role_code FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL
             ORDER BY u.created_at DESC LIMIT 5"
        );

        $this->view('admin/dashboard', [
            'pageTitle'     => 'Admin Dashboard — GlowBook',
            'stats'         => $stats,
            'pendingSalons' => $pendingSalons,
            'recentUsers'   => $recentUsers,
        ]);
    }

    // GET /admin/salons
    public function salons(): void
    {
        $this->requireRole(['PLATFORM_ADMIN','COMPLIANCE','SUPPORT']);
        $page   = max(1, (int)$this->query('page', 1));
        $status = $this->query('status');
        $search = $this->query('q');
        $result = $this->businesses->adminList($page, 20, $status, $search);

        $this->view('admin/salons', [
            'pageTitle' => 'Quản lý salon',
            'result'    => $result,
            'status'    => $status,
            'search'    => $search,
        ]);
    }

    // GET /admin/users
    public function users(): void
    {
        $this->requireRole(['PLATFORM_ADMIN','SUPPORT']);
        $page   = max(1, (int)$this->query('page', 1));
        $search = $this->query('q');
        $result = $this->users->listWithRole($page, 20, $search);

        $this->view('admin/users', [
            'pageTitle' => 'Quản lý người dùng',
            'result'    => $result,
            'search'    => $search,
        ]);
    }

    // GET /admin/reviews
    public function reviews(): void
    {
        $this->requireRole(['PLATFORM_ADMIN','COMPLIANCE']);
        $db = \Database::getInstance();
        $page   = max(1, (int)$this->query('page', 1));
        $offset = ($page - 1) * 20;
        $reviews = $db->query(
            "SELECT r.*, u.full_name AS customer_name, bu.name AS business_name
             FROM reviews r
             JOIN bookings bk ON bk.id = r.booking_id
             JOIN branches br ON br.id = bk.branch_id
             JOIN businesses bu ON bu.id = br.business_id
             LEFT JOIN customer_profiles cp ON cp.id = r.customer_id
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE r.deleted_at IS NULL
             ORDER BY r.created_at DESC LIMIT 20 OFFSET {$offset}",
        );

        $this->view('admin/reviews', [
            'pageTitle' => 'Kiểm duyệt đánh giá',
            'reviews'   => $reviews,
            'page'      => $page,
        ]);
    }

    // GET /admin/settings
    public function settings(): void
    {
        $this->requireRole(['PLATFORM_ADMIN']);
        $db       = \Database::getInstance();
        $settings = $db->query("SELECT * FROM platform_settings ORDER BY `key`");
        $this->view('admin/settings', [
            'pageTitle' => 'Cài đặt platform',
            'settings'  => $settings,
        ]);
    }

    // POST /admin/salons/{id}/approve
    public function approveSalon(int $id): void
    {
        $this->requireRole(['PLATFORM_ADMIN','COMPLIANCE']);
        $this->verifyCsrf();
        $db = \Database::getInstance();
        $db->execute(
            "UPDATE businesses SET status = 'ACTIVE', reviewed_at = NOW(), review_note = ? WHERE id = ?",
            [$this->input('note'), $id]
        );
        $this->flash('success', 'Đã duyệt salon #' . $id);
        $this->redirect('/admin/salons');
    }

    // POST /admin/salons/{id}/reject
    public function rejectSalon(int $id): void
    {
        $this->requireRole(['PLATFORM_ADMIN','COMPLIANCE']);
        $this->verifyCsrf();
        $db = \Database::getInstance();
        $db->execute(
            "UPDATE businesses SET status = 'REJECTED', reviewed_at = NOW(), review_note = ? WHERE id = ?",
            [$this->input('reason'), $id]
        );
        $this->flash('success', 'Đã từ chối salon #' . $id);
        $this->redirect('/admin/salons');
    }
}
