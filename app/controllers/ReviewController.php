<?php
// ============================================================
//  app/controllers/ReviewController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BookingModel.php';

class ReviewController extends Controller
{
    private BookingModel $bookings;

    public function __construct()
    {
        $this->bookings = new BookingModel();
    }

    // POST /reviews/create
    public function create(): void
    {
        $this->requireRole(['CUSTOMER']);
        $this->verifyCsrf();

        $bookingId     = (int)$this->input('booking_id');
        $overallRating = max(1, min(5, (int)$this->input('overall_rating', 5)));
        $comment       = mb_substr((string)$this->input('comment'), 0, 2000);
        $isAnonymous   = isset($_POST['is_anonymous']) ? 1 : 0;

        $booking = $this->bookings->findById($bookingId);
        if (!$booking) $this->abort(404, 'Lịch hẹn không tồn tại.');

        $db = \Database::getInstance();
        $cp = $db->queryOne("SELECT id FROM customer_profiles WHERE user_id = ? LIMIT 1", [Session::userId()]);
        if (!$cp || $booking['customer_id'] != $cp['id']) {
            $this->abort(403, 'Bạn không có quyền đánh giá lịch hẹn này.');
        }

        if ($booking['status'] !== 'COMPLETED') {
            $this->flash('error', 'Chỉ có thể đánh giá lịch hẹn đã hoàn thành.');
            $this->redirect('/customer/bookings/' . $bookingId);
            return;
        }

        // Kiểm tra xem đã đánh giá chưa
        $existing = $db->queryOne("SELECT id FROM reviews WHERE booking_id = ? LIMIT 1", [$bookingId]);
        if ($existing) {
            $this->flash('warning', 'Bạn đã đánh giá lịch hẹn này rồi.');
            $this->redirect('/customer/bookings/' . $bookingId);
            return;
        }

        try {
            $db->execute(
                "INSERT INTO reviews (booking_id, customer_id, branch_id, overall_rating, comment, is_anonymous, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'PUBLISHED', NOW(), NOW())",
                [$bookingId, $cp['id'], $booking['branch_id'], $overallRating, $comment ?: null, $isAnonymous]
            );
            $this->flash('success', 'Cảm ơn bạn đã gửi đánh giá dịch vụ!');
        } catch (\PDOException $e) {
            // reviews.booking_id là UNIQUE ở tầng DB (xem schema.sql). Nếu 2 tab
            // cùng gửi đánh giá gần như đồng thời, câu SELECT kiểm tra phía trên
            // có thể không kịp thấy dòng của nhau — DB sẽ chặn ở đây (mã lỗi 1062).
            if ((int)$e->errorInfo[1] === 1062) {
                $this->flash('warning', 'Bạn đã đánh giá lịch hẹn này rồi.');
            } else {
                error_log('[review.create] ' . $e->getMessage());
                $this->flash('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
            }
        }

        $this->redirect('/customer/bookings/' . $bookingId);
    }
}
