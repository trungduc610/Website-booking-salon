<?php
// ============================================================
//  app/controllers/BookingController.php  — BẢN VÁ
//  Thay thế toàn bộ file cũ.
//
//  Sửa các lỗi NGHIÊM TRỌNG của bản cũ:
//   1. Không hề kiểm tra trùng lịch  → nay gọi BookingModel::createFull() có khoá
//   2. confirm/reject/complete KHÔNG kiểm tra booking có thuộc salon của mình không
//      → bất kỳ chủ salon nào cũng sửa được lịch của salon khác (IDOR)
//   3. Không kiểm tra máy trạng thái → có thể COMPLETE một lịch đã CANCELLED
//   4. Không validate định dạng ngày/giờ, SĐT, giờ mở cửa, lead time
//   5. $endTime bị tràn sang ngày hôm sau mà không báo lỗi
//   6. Dịch vụ trùng lặp bị cộng tiền nhiều lần
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BookingModel.php';
require_once BASE_PATH . '/app/models/ServiceModel.php';

class BookingController extends Controller
{
    private BookingModel $bookings;

    public function __construct()
    {
        $this->bookings = new BookingModel();
    }

    // ========================================================
    //  POST /book  → Tạo booking mới
    // ========================================================
    public function create(): void
    {
        $this->verifyCsrf();

        $branchId  = (int)$this->input('branch_id', 0);
        $date      = trim((string)$this->input('appointment_date'));
        $startTime = trim((string)$this->input('appointment_start_time'));
        $note      = mb_substr((string)$this->input('note'), 0, 1000);

        $back = '/book?branch_id=' . $branchId;

        // ---- 1. Validate định dạng ĐẦU VÀO --------------------------------
        if ($branchId <= 0) {
            $this->fail('Chi nhánh không hợp lệ.', '/');
        }
        if (!isValidDate($date)) {
            $this->fail('Ngày hẹn không đúng định dạng.', $back);
        }
        if (!isValidTime($startTime)) {
            $this->fail('Giờ hẹn không đúng định dạng.', $back);
        }
        $startTime = normalizeTime($startTime);          // "9:00" → "09:00:00"

        // service_ids phải là mảng số nguyên, loại trùng
        $rawIds = $_POST['service_ids'] ?? [];
        if (!is_array($rawIds)) $rawIds = [$rawIds];
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $rawIds))));
        if (empty($serviceIds)) {
            $this->fail('Vui lòng chọn ít nhất một dịch vụ.', $back);
        }
        if (count($serviceIds) > 10) {
            $this->fail('Mỗi lần đặt tối đa 10 dịch vụ.', $back);
        }

        $staffId = (int)($_POST['staff_id'] ?? 0);

        // ---- 2. Chi nhánh phải tồn tại và đang hoạt động -------------------
        $db     = \Database::getInstance();
        $branch = $db->queryOne(
            "SELECT br.id, br.business_id FROM branches br
             JOIN businesses bu ON bu.id = br.business_id
             WHERE br.id = ? AND br.operational_status = 'ACTIVE'
               AND br.deleted_at IS NULL AND bu.status = 'ACTIVE' AND bu.deleted_at IS NULL
             LIMIT 1",
            [$branchId]
        );
        if (!$branch) {
            $this->fail('Chi nhánh này hiện không nhận đặt lịch.', '/');
        }

        // ---- 3. Nhân viên (nếu chọn) phải thuộc ĐÚNG chi nhánh này ---------
        if ($staffId > 0) {
            $staff = $db->queryOne(
                "SELECT id FROM staff_profiles
                 WHERE id = ? AND branch_id = ? AND is_bookable = 1
                   AND status = 'ACTIVE' AND deleted_at IS NULL LIMIT 1",
                [$staffId, $branchId]
            );
            if (!$staff) {
                $this->fail('Nhân viên bạn chọn không khả dụng tại chi nhánh này.', $back);
            }
        } else {
            $staffId = 0;
        }

        // ---- 4. Lấy giá & thời lượng TỪ DATABASE (không tin client) --------
        $svcModel      = new ServiceModel();
        $services      = [];
        $totalAmount   = 0.0;
        $totalDuration = 0;

        foreach ($serviceIds as $sid) {
            $svc = $svcModel->findForBranch($sid, $branchId);
            if (!$svc || (int)$svc['bookable'] !== 1 || $svc['status'] !== 'ACTIVE') {
                $this->fail('Có dịch vụ không hợp lệ trong lựa chọn của bạn.', $back);
            }
            $services[] = [
                'service_id'       => (int)$svc['id'],
                'name'             => $svc['name'],
                'price'            => (float)$svc['price'],
                'duration_minutes' => (int)$svc['duration_minutes'],
                'staff_id'         => $staffId ?: null,
            ];
            $totalAmount   += (float)$svc['price'];
            $totalDuration += (int)$svc['duration_minutes'];
        }

        if ($totalDuration <= 0) {
            $this->fail('Thời lượng dịch vụ không hợp lệ.', $back);
        }

        // ---- 5. Tính giờ kết thúc, CHẶN tràn qua nửa đêm -------------------
        $startSec = timeToSeconds($startTime);
        $endSec   = $startSec + $totalDuration * 60;
        if ($endSec > 24 * 3600) {
            $this->fail(
                'Tổng thời lượng ' . formatDuration($totalDuration) .
                ' vượt quá thời gian còn lại trong ngày. Vui lòng chọn giờ sớm hơn.',
                $back
            );
        }
        $endTime = secondsToTime($endSec);

        // ---- 6. Áp chính sách đặt lịch của chi nhánh -----------------------
        $policy   = $this->bookings->policyFor($branchId);
        $buffer   = (int)($policy['default_buffer_minutes'] ?? 0);
        $leadMin  = (int)($policy['lead_time_minutes'] ?? 0);
        $horizon  = (int)($policy['booking_horizon_days'] ?? 90);

        $startTs = strtotime($date . ' ' . $startTime);
        if ($startTs === false) {
            $this->fail('Thời điểm hẹn không hợp lệ.', $back);
        }
        if ($startTs < time() + $leadMin * 60) {
            $this->fail(
                $leadMin > 0
                    ? 'Bạn cần đặt trước ít nhất ' . formatDuration($leadMin) . '.'
                    : 'Không thể đặt lịch cho thời điểm đã qua.',
                $back
            );
        }
        if ($startTs > strtotime("+{$horizon} days")) {
            $this->fail("Chỉ nhận đặt lịch trong vòng {$horizon} ngày tới.", $back);
        }

        // ---- 7. Phải nằm trong giờ mở cửa ----------------------------------
        if (!$this->bookings->isWithinWorkingHours($branchId, $date, $startTime, $endTime)) {
            $this->fail(
                'Khung giờ ' . substr($startTime, 0, 5) . '–' . substr($endTime, 0, 5) .
                ' nằm ngoài giờ mở cửa của chi nhánh.',
                $back
            );
        }

        // ---- 8. Thông tin khách --------------------------------------------
        $customerId = null;
        $contact    = [];

        if (Session::isLoggedIn()) {
            $cp = $db->queryOne("SELECT id FROM customer_profiles WHERE user_id = ? LIMIT 1", [Session::userId()]);
            if (!$cp) {
                $this->fail('Tài khoản của bạn chưa có hồ sơ khách hàng.', '/customer/profile');
            }
            $customerId = (int)$cp['id'];
        } else {
            $guestName  = trim((string)$this->input('guest_name'));
            $guestPhone = normalizeVnPhone((string)$this->input('guest_phone'));
            $guestEmail = trim((string)$this->input('guest_email'));

            if (mb_strlen($guestName) < 2 || mb_strlen($guestName) > 150) {
                $this->fail('Vui lòng nhập họ tên hợp lệ (2–150 ký tự).', $back);
            }
            if ($guestPhone === null) {
                $this->fail('Số điện thoại không hợp lệ. Ví dụ đúng: 0912345678 hoặc +84912345678.', $back);
            }
            if ($guestEmail !== '' && !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Email không hợp lệ.', $back);
            }
            $contact = ['full_name' => $guestName, 'phone' => $guestPhone, 'email' => $guestEmail ?: null];
        }

        // ---- 9. Ghi booking (transaction + khoá chống double-booking) -------
        try {
            $bookingId = $this->bookings->createFull(
                [
                    'customer_id'            => $customerId,
                    'branch_id'              => $branchId,
                    'appointment_date'       => $date,
                    'appointment_start_time' => $startTime,
                    'appointment_end_time'   => $endTime,
                    'total_amount'           => $totalAmount,
                    'final_amount'           => $totalAmount,
                    'note'                   => $note ?: null,
                    'source'                 => 'ONLINE_WEB',
                    'pending_expires_at'     => date('Y-m-d H:i:s', time() + 1800),
                ],
                $services,
                $contact,
                $buffer
            );

            $booking = $this->bookings->findDetail($bookingId);

            // Không nhúng thẻ HTML vào flash — showFlash() escape toàn bộ nội dung.
            $this->flash('success', 'Đặt lịch thành công! Mã lịch hẹn của bạn: ' . $booking['booking_code']);

            if (Session::isLoggedIn()) {
                $this->redirect('/customer/bookings/' . $bookingId);
            } else {
                // Lưu mã vào session thay vì đưa lên URL → tránh lộ thông tin khách qua Referer/log
                Session::set('last_booking_code', $booking['booking_code']);
                $this->redirect('/book/success');
            }

        } catch (BookingConflictException $e) {
            $this->fail($e->getMessage(), $back);
        } catch (\Throwable $e) {
            if (APP_DEBUG) throw $e;
            error_log('[booking.create] ' . $e->getMessage());
            $this->fail('Có lỗi xảy ra khi đặt lịch. Vui lòng thử lại.', $back);
        }
    }

    // ========================================================
    //  POST /customer/bookings/{id}/cancel
    // ========================================================
    public function cancel(int $id): void
    {
        $this->requireRole(['CUSTOMER']);
        $this->verifyCsrf();

        $booking = $this->bookings->findById($id);
        if (!$booking) $this->abort(404);

        $db = \Database::getInstance();
        $cp = $db->queryOne("SELECT id FROM customer_profiles WHERE user_id = ? LIMIT 1", [Session::userId()]);
        if (!$cp || (int)$booking['customer_id'] !== (int)$cp['id']) {
            $this->abort(403, 'Bạn không có quyền huỷ lịch hẹn này.');
        }

        if (!BookingModel::canTransition($booking['status'], 'CANCELLED')) {
            $this->fail('Lịch hẹn ở trạng thái này không thể huỷ.', '/customer/bookings/' . $id);
        }

        // Cảnh báo phí huỷ trễ theo chính sách chi nhánh
        $policy   = $this->bookings->policyFor((int)$booking['branch_id']);
        $deadline = strtotime($booking['appointment_date'] . ' ' . $booking['appointment_start_time'])
                    - ((int)$policy['cancellation_hours'] * 3600);
        $late     = time() > $deadline;

        $reason = mb_substr(trim((string)$this->input('reason')), 0, 500);
        $ok = $this->bookings->changeStatus($id, 'CANCELLED', (int)Session::userId(), $reason);

        if (!$ok) {
            $this->fail('Không thể huỷ lịch hẹn lúc này.', '/customer/bookings/' . $id);
        }

        $this->flash(
            $late ? 'warning' : 'success',
            $late
                ? 'Đã huỷ lịch hẹn. Bạn huỷ trong vòng ' . $policy['cancellation_hours'] .
                  ' giờ trước giờ hẹn nên có thể phát sinh phí huỷ trễ theo chính sách của salon.'
                : 'Đã huỷ lịch hẹn thành công.'
        );
        $this->redirect('/customer/bookings');
    }

    // ========================================================
    //  Các thao tác phía SALON
    // ========================================================

    public function confirm(int $id): void   { $this->salonTransition($id, 'CONFIRMED',   'Đã xác nhận lịch hẹn.'); }
    public function reject(int $id): void    { $this->salonTransition($id, 'REJECTED',    'Đã từ chối lịch hẹn.'); }
    public function checkIn(int $id): void   { $this->salonTransition($id, 'CHECKED_IN',  'Đã ghi nhận khách đến.'); }
    public function start(int $id): void     { $this->salonTransition($id, 'IN_PROGRESS', 'Đã bắt đầu phục vụ.'); }
    public function complete(int $id): void  { $this->salonTransition($id, 'COMPLETED',   'Đã đánh dấu hoàn thành.'); }
    public function noShow(int $id): void    { $this->salonTransition($id, 'NO_SHOW',     'Đã đánh dấu khách không đến.'); }

    /**
     * Xử lý chung cho mọi chuyển trạng thái phía salon.
     *
     * ĐÂY LÀ CHỖ VÁ LỖI IDOR: bản cũ chỉ kiểm tra "có role salon hay không",
     * KHÔNG kiểm tra booking có thuộc salon của người đang đăng nhập.
     * Kẻ tấn công chỉ cần đổi {id} trên URL là thao túng được lịch của salon khác.
     */
    private function salonTransition(int $id, string $newStatus, string $successMsg): void
    {
        // STAFF chỉ được cập nhật tiến trình phục vụ, không được xác nhận/từ chối đơn
        $roles = in_array($newStatus, ['CONFIRMED', 'REJECTED'], true)
            ? ['BUSINESS_OWNER', 'BRANCH_MANAGER', 'RECEPTIONIST']
            : ['BUSINESS_OWNER', 'BRANCH_MANAGER', 'RECEPTIONIST', 'STAFF'];

        $this->requireRole($roles);
        $this->verifyCsrf();

        // Lấy chi nhánh mà user hiện tại thực sự có quyền
        $branchIds = $this->accessibleBranchIds();
        if (empty($branchIds)) {
            $this->abort(403, 'Tài khoản của bạn chưa gắn với chi nhánh nào.');
        }

        $booking = $this->bookings->findById($id);
        if (!$booking) $this->abort(404, 'Lịch hẹn không tồn tại.');

        if (!in_array((int)$booking['branch_id'], $branchIds, true)) {
            $this->abort(403, 'Lịch hẹn này không thuộc salon của bạn.');
        }

        $note = mb_substr(trim((string)$this->input('reason')), 0, 500);
        $ok   = $this->bookings->changeStatus($id, $newStatus, (int)Session::userId(), $note);

        if (!$ok) {
            $this->fail(
                'Không thể chuyển lịch hẹn từ "' . bookingStatusText($booking['status']) .
                '" sang "' . bookingStatusText($newStatus) . '".',
                '/salon/bookings'
            );
        }

        $this->flash('success', $successMsg);
        $this->redirect('/salon/bookings');
    }

    /** Danh sách branch_id mà user hiện tại được phép thao tác */
    private function accessibleBranchIds(): array
    {
        $db     = \Database::getInstance();
        $userId = Session::userId();

        // Chủ salon → tất cả chi nhánh của mọi business họ sở hữu
        $rows = $db->query(
            "SELECT br.id FROM branches br
             JOIN businesses b            ON b.id  = br.business_id
             JOIN business_owner_profiles op ON op.id = b.owner_id
             WHERE op.user_id = ? AND br.deleted_at IS NULL AND b.deleted_at IS NULL",
            [$userId]
        );

        // Nhân sự salon → chi nhánh được gán (hoặc toàn bộ business nếu branch_id NULL)
        $rows = array_merge($rows, $db->query(
            "SELECT br.id FROM salon_members sm
             JOIN branches br ON br.business_id = sm.business_id
                             AND (sm.branch_id IS NULL OR sm.branch_id = br.id)
             WHERE sm.user_id = ? AND sm.is_active = 1 AND sm.deleted_at IS NULL
               AND br.deleted_at IS NULL",
            [$userId]
        ));

        return array_values(array_unique(array_map(fn($r) => (int)$r['id'], $rows)));
    }

    /** Helper: báo lỗi + quay lại, dùng để rút gọn các nhánh validate */
    private function fail(string $message, string $url): void
    {
        $this->flash('error', $message);
        $this->redirect($url);
    }
}
