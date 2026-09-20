<?php
// ============================================================
//  app/controllers/CustomerController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BookingModel.php';
require_once BASE_PATH . '/app/models/UserModel.php';

class CustomerController extends Controller
{
    private BookingModel $bookings;
    private UserModel    $users;

    public function __construct()
    {
        $this->bookings = new BookingModel();
        $this->users    = new UserModel();
    }

    // GET /customer/dashboard
    public function dashboard(): void
    {
        $this->requireRole(['CUSTOMER']);
        $customerId = $this->getCustomerId();

        $db = \Database::getInstance();

        $recentBookings = $db->query(
            "SELECT bk.*, br.name AS branch_name, bu.name AS business_name, bu.logo
             FROM bookings bk
             JOIN branches   br ON br.id = bk.branch_id
             JOIN businesses bu ON bu.id = br.business_id
             WHERE bk.customer_id = ? AND bk.deleted_at IS NULL
             ORDER BY bk.created_at DESC LIMIT 5",
            [$customerId]
        );

        $stats = [
            'total'     => $this->bookings->count('customer_id = ?', [$customerId]),
            'completed' => $this->bookings->count("customer_id = ? AND status = 'COMPLETED'", [$customerId]),
            'pending'   => $this->bookings->count("customer_id = ? AND status IN ('PENDING','CONFIRMED')", [$customerId]),
        ];

        $this->view('customer/dashboard', [
            'pageTitle'      => 'Dashboard — GlowBook',
            'recentBookings' => $recentBookings,
            'stats'          => $stats,
        ]);
    }

    // GET /customer/bookings
    public function myBookings(): void
    {
        $this->requireRole(['CUSTOMER']);
        $customerId = $this->getCustomerId();
        $page       = max(1, (int)$this->query('page', 1));
        $result     = $this->bookings->findByCustomer($customerId, $page);

        $this->view('customer/bookings', [
            'pageTitle' => 'Lịch hẹn của tôi',
            'result'    => $result,
        ]);
    }

    // GET /customer/bookings/{id}
    public function bookingDetail(int $id): void
    {
        $this->requireRole(['CUSTOMER']);
        $customerId = $this->getCustomerId();
        $booking    = $this->bookings->findDetail($id);

        if (!$booking || $booking['customer_id'] != $customerId) $this->abort(404);

        $this->view('customer/booking_detail', [
            'pageTitle' => 'Chi tiết lịch hẹn #' . $booking['booking_code'],
            'booking'   => $booking,
        ]);
    }

    // GET /customer/profile
    public function profile(): void
    {
        $this->requireLogin();
        $user = $this->users->findWithRole(Session::userId());
        $this->view('customer/profile', [
            'pageTitle' => 'Hồ sơ cá nhân',
            'user'      => $user,
        ]);
    }

    // POST /customer/profile/update
    public function updateProfile(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $data = [
            'full_name'   => $this->input('full_name'),
            'phone'       => $this->rawInput('phone'),
            'date_of_birth' => $this->rawInput('date_of_birth') ?: null,
            'gender'      => $this->rawInput('gender') ?: null,
        ];

        // Upload avatar nếu có
        $avatar = uploadImage('avatar', 'avatars');
        if ($avatar) $data['avatar'] = $avatar;

        $this->users->update(Session::userId(), $data);
        Session::set('user_name', $data['full_name']);

        $this->flash('success', 'Cập nhật hồ sơ thành công!');
        $this->redirect('/customer/profile');
    }

    // GET /customer/vouchers
    public function myVouchers(): void
    {
        $this->requireRole(['CUSTOMER']);
        $customerId = $this->getCustomerId();
        $db = \Database::getInstance();
        $vouchers = $db->query(
            "SELECT cv.*, v.code, v.name, v.discount_type, v.discount_value, v.end_date
             FROM customer_vouchers cv
             JOIN vouchers v ON v.id = cv.voucher_id
             WHERE cv.customer_id = ? AND cv.status = 'ACTIVE'
             ORDER BY cv.acquired_at DESC",
            [$customerId]
        );
        $this->view('customer/vouchers', [
            'pageTitle' => 'Voucher của tôi',
            'vouchers'  => $vouchers,
        ]);
    }

    // ---- Private helper ------------------------------------
    private function getCustomerId(): int
    {
        $db = \Database::getInstance();
        $cp = $db->queryOne("SELECT id FROM customer_profiles WHERE user_id = ? LIMIT 1", [Session::userId()]);
        if (!$cp) $this->abort(403, 'Không tìm thấy hồ sơ khách hàng.');
        return (int)$cp['id'];
    }
}
