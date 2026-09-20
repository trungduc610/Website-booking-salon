<?php
// ============================================================
//  app/models/BookingModel.php  — BẢN VÁ BẢO MẬT & LOGIC
//  Thay thế toàn bộ file cũ.
//
//  Thay đổi chính:
//   1. Thêm hasConflict()      — phát hiện trùng lịch (chi nhánh + nhân viên)
//   2. createFull()            — khoá hàng branch (FOR UPDATE) rồi mới kiểm tra
//                                trùng lịch bên trong transaction  → chống race condition
//   3. changeStatus()          — ép đúng máy trạng thái (state machine)
//   4. findByBranch()          — sửa lỗi COUNT dùng alias `bk.` gây SQL error
//   5. findDetail()/findByBranch() — chống nhân dòng do LEFT JOIN booking_contacts
// ============================================================
require_once BASE_PATH . '/core/Model.php';

class BookingModel extends Model
{
    protected string $table = 'bookings';

    /** Các trạng thái vẫn còn "giữ chỗ" trên lịch */
    public const ACTIVE_STATUSES = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'IN_PROGRESS'];

    /**
     * Máy trạng thái hợp lệ: từ trạng thái nào được sang trạng thái nào.
     * Mọi chuyển đổi không nằm trong bảng này đều bị từ chối.
     */
    public const TRANSITIONS = [
        'PENDING'     => ['CONFIRMED', 'REJECTED', 'CANCELLED', 'EXPIRED'],
        'CONFIRMED'   => ['CHECKED_IN', 'IN_PROGRESS', 'CANCELLED', 'NO_SHOW'],
        'CHECKED_IN'  => ['IN_PROGRESS', 'CANCELLED', 'NO_SHOW'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED'   => [],
        'CANCELLED'   => [],
        'REJECTED'    => [],
        'NO_SHOW'     => [],
        'EXPIRED'     => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Tạo mã booking ngẫu nhiên, đủ dài để không thể dò (VD: GLW-20250830-8F3A21C4) */
    public static function generateCode(): string
    {
        return 'GLW-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    // --------------------------------------------------------
    //  1. KIỂM TRA TRÙNG LỊCH
    // --------------------------------------------------------

    /**
     * Kiểm tra khung giờ có bị trùng không.
     *
     * Quy tắc trùng khoảng thời gian (interval overlap):
     *      newStart < existingEnd + buffer  AND  newEnd + buffer > existingStart
     *
     * @param int      $branchId  Chi nhánh
     * @param string   $date      Y-m-d
     * @param string   $start     H:i:s
     * @param string   $end       H:i:s
     * @param int|null $staffId   Nếu có → kiểm tra riêng cho nhân viên đó
     * @param int      $buffer    Phút nghỉ giữa 2 lịch
     * @param int|null $excludeId Bỏ qua 1 booking (dùng khi dời lịch)
     *
     * @return array|false  Trả về booking bị trùng đầu tiên, hoặc false nếu trống lịch
     */
    public function findConflict(
        int $branchId,
        string $date,
        string $start,
        string $end,
        ?int $staffId = null,
        int $buffer = 0,
        ?int $excludeId = null
    ): array|false {
        $statusIn = "'" . implode("','", self::ACTIVE_STATUSES) . "'"; // hằng số nội bộ, không phải input

        $params = [$branchId, $date];
        $join   = '';
        $extra  = '';

        if ($staffId) {
            // Chỉ tính các lịch mà nhân viên này thực sự được phân công
            $join    = "JOIN booking_services bs ON bs.booking_id = bk.id
                        AND bs.staff_id = ?
                        AND bs.status NOT IN ('CANCELLED','SKIPPED')";
            $params[] = $staffId;
        }

        // buffer tính bằng giây
        $params[] = $buffer * 60;   // cộng vào end của lịch cũ
        $params[] = $end;
        $params[] = $start;
        $params[] = $buffer * 60;   // cộng vào end của lịch mới

        if ($excludeId) {
            $extra    = ' AND bk.id <> ?';
            $params[] = $excludeId;
        }

        $sql = "SELECT bk.id, bk.booking_code, bk.appointment_start_time, bk.appointment_end_time
                FROM bookings bk
                {$join}
                WHERE bk.branch_id = ?
                  AND bk.appointment_date = ?
                  AND bk.deleted_at IS NULL
                  AND bk.status IN ({$statusIn})
                  AND ADDTIME(bk.appointment_end_time, SEC_TO_TIME(?)) > ?
                  AND bk.appointment_start_time < ADDTIME(?, SEC_TO_TIME(?))
                  {$extra}
                LIMIT 1";

        return $this->db->queryOne($sql, $params);
    }

    /** Đếm số nhân viên còn nhận khách trong khung giờ (dùng khi khách chọn "Bất kỳ") */
    public function countFreeStaff(int $branchId, string $date, string $start, string $end, int $buffer = 0): int
    {
        $staffList = $this->db->query(
            "SELECT id FROM staff_profiles
             WHERE branch_id = ? AND is_bookable = 1 AND status = 'ACTIVE' AND deleted_at IS NULL",
            [$branchId]
        );

        $free = 0;
        foreach ($staffList as $st) {
            $onLeave = $this->db->queryOne(
                "SELECT id FROM staff_leaves
                 WHERE staff_id = ? AND status = 'APPROVED'
                   AND start_at < CONCAT(?, ' ', ?) AND end_at > CONCAT(?, ' ', ?)
                 LIMIT 1",
                [$st['id'], $date, $end, $date, $start]
            );
            if ($onLeave) continue;

            if (!$this->findConflict($branchId, $date, $start, $end, (int)$st['id'], $buffer)) {
                $free++;
            }
        }
        return $free;
    }

    /** Chi nhánh có mở cửa và phủ hết khung giờ này không? */
    public function isWithinWorkingHours(int $branchId, string $date, string $start, string $end): bool
    {
        $dow = (int)date('w', strtotime($date)); // 0 = CN

        $wh = $this->db->queryOne(
            "SELECT open_time, close_time, is_closed
             FROM branch_working_hours WHERE branch_id = ? AND day_of_week = ? LIMIT 1",
            [$branchId, $dow]
        );
        if (!$wh || (int)$wh['is_closed'] === 1) return false;
        if ($start < $wh['open_time'] || $end > $wh['close_time']) return false;

        // Ngày nghỉ lễ / đóng cửa đột xuất
        $holiday = $this->db->queryOne(
            "SELECT id FROM branch_holidays WHERE branch_id = ? AND date = ? LIMIT 1",
            [$branchId, $date]
        );
        return !$holiday;
    }

    /** Lấy chính sách đặt lịch của chi nhánh (có giá trị mặc định an toàn) */
    public function policyFor(int $branchId): array
    {
        $p = $this->db->queryOne(
            "SELECT * FROM branch_booking_policies WHERE branch_id = ? LIMIT 1",
            [$branchId]
        );
        return $p ?: [
            'lead_time_minutes'      => 60,
            'booking_horizon_days'   => 90,
            'cancellation_hours'     => 24,
            'default_buffer_minutes' => 0,
        ];
    }

    // --------------------------------------------------------
    //  2. TẠO BOOKING AN TOÀN (chống double-booking)
    // --------------------------------------------------------

    /**
     * Tạo booking đầy đủ trong 1 transaction, có khoá chống race condition.
     *
     * @throws BookingConflictException  khi khung giờ đã có người đặt
     */
    public function createFull(array $booking, array $services, array $contact = [], int $buffer = 0): int
    {
        $this->db->beginTransaction();
        try {
            // (A) KHOÁ HÀNG CHI NHÁNH.
            //     MySQL không thể tạo UNIQUE INDEX trên "khoảng thời gian chồng lấn",
            //     nên ta tuần tự hoá mọi yêu cầu đặt lịch của cùng 1 chi nhánh bằng row lock.
            //     Hai request đồng thời → request thứ 2 phải chờ, sau đó mới kiểm tra trùng
            //     → không còn cửa sổ race condition giữa lúc CHECK và lúc INSERT.
            $this->db->query("SELECT id FROM branches WHERE id = ? FOR UPDATE", [$booking['branch_id']]);

            // (B) Kiểm tra trùng lịch theo nhân viên (nếu khách chọn) hoặc theo sức chứa chi nhánh
            $staffId = null;
            foreach ($services as $svc) {
                if (!empty($svc['staff_id'])) { $staffId = (int)$svc['staff_id']; break; }
            }

            $conflict = $this->findConflict(
                (int)$booking['branch_id'],
                $booking['appointment_date'],
                $booking['appointment_start_time'],
                $booking['appointment_end_time'],
                $staffId,
                $buffer
            );

            if ($conflict) {
                throw new BookingConflictException(
                    'Khung giờ này đã có khách đặt. Vui lòng chọn giờ khác.'
                );
            }

            if (!$staffId) {
                // Khách chọn "Bất kỳ" → phải còn ít nhất 1 KTV rảnh
                $free = $this->countFreeStaff(
                    (int)$booking['branch_id'],
                    $booking['appointment_date'],
                    $booking['appointment_start_time'],
                    $booking['appointment_end_time'],
                    $buffer
                );
                if ($free < 1) {
                    throw new BookingConflictException(
                        'Chi nhánh đã kín lịch vào khung giờ này. Vui lòng chọn giờ khác.'
                    );
                }
            }

            // (C) Ghi booking
            $booking['booking_code'] = self::generateCode();
            $booking['status']       = 'PENDING';
            $booking['created_at']   = date('Y-m-d H:i:s');
            $booking['updated_at']   = date('Y-m-d H:i:s');

            $cols = implode('`, `', array_keys($booking));
            $phs  = implode(', ', array_fill(0, count($booking), '?'));
            $bookingId = (int)$this->db->insert(
                "INSERT INTO bookings (`{$cols}`) VALUES ({$phs})",
                array_values($booking)
            );

            if (!empty($contact)) {
                $this->db->execute(
                    "INSERT INTO booking_contacts (booking_id, full_name, phone, email, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$bookingId, $contact['full_name'], $contact['phone'] ?? null, $contact['email'] ?? null]
                );
            }

            foreach ($services as $i => $svc) {
                $this->db->execute(
                    "INSERT INTO booking_services
                     (booking_id, service_id, staff_id, service_name_snapshot, price_at_booking,
                      duration_minutes, sort_order, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'SCHEDULED', NOW())",
                    [
                        $bookingId,
                        $svc['service_id'],
                        $svc['staff_id'] ?? null,
                        $svc['name'],
                        $svc['price'],
                        $svc['duration_minutes'],
                        $i,
                    ]
                );
            }

            $this->db->execute(
                "INSERT INTO booking_status_histories (booking_id, status, created_at)
                 VALUES (?, 'PENDING', NOW())",
                [$bookingId]
            );

            $this->db->commit();
            return $bookingId;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // --------------------------------------------------------
    //  3. ĐỔI TRẠNG THÁI THEO MÁY TRẠNG THÁI
    // --------------------------------------------------------

    /**
     * Đổi trạng thái booking. Chỉ cho phép các bước hợp lệ.
     *
     * @return bool true nếu đổi thành công, false nếu bước chuyển không hợp lệ
     */
    public function changeStatus(int $id, string $newStatus, int $changedBy = 0, string $note = ''): bool
    {
        $this->db->beginTransaction();
        try {
            // Khoá hàng booking để 2 nhân viên bấm cùng lúc không ghi đè nhau
            $current = $this->db->queryOne(
                "SELECT id, status FROM bookings WHERE id = ? AND deleted_at IS NULL FOR UPDATE",
                [$id]
            );
            if (!$current) { $this->db->rollBack(); return false; }

            if (!self::canTransition($current['status'], $newStatus)) {
                $this->db->rollBack();
                return false;
            }

            $sql    = "UPDATE bookings SET status = ?, updated_at = NOW()";
            $params = [$newStatus];

            // Huỷ / từ chối → ghi lại lý do và thời điểm, đồng thời trả slot về cho lịch
            if (in_array($newStatus, ['CANCELLED', 'REJECTED'], true)) {
                $sql .= ", cancel_reason = ?, cancelled_at = NOW(), cancelled_by = ?";
                $params[] = $note ?: null;
                $params[] = $changedBy ?: null;
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;
            $this->db->execute($sql, $params);

            // Giải phóng chỗ: các dòng dịch vụ cũng phải rời khỏi trạng thái giữ chỗ,
            // nếu không findConflict() vẫn coi nhân viên đang bận.
            if (in_array($newStatus, ['CANCELLED', 'REJECTED', 'NO_SHOW', 'EXPIRED'], true)) {
                $this->db->execute(
                    "UPDATE booking_services SET status = 'CANCELLED'
                     WHERE booking_id = ? AND status NOT IN ('COMPLETED')",
                    [$id]
                );
            }
            if ($newStatus === 'COMPLETED') {
                $this->db->execute(
                    "UPDATE booking_services SET status = 'COMPLETED', item_end_at = NOW()
                     WHERE booking_id = ? AND status NOT IN ('CANCELLED','SKIPPED')",
                    [$id]
                );
            }

            $this->db->execute(
                "INSERT INTO booking_status_histories (booking_id, status, changed_by, note, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$id, $newStatus, $changedBy ?: null, $note ?: null]
            );

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Dọn các booking PENDING quá hạn giữ chỗ → trả slot về cho khách khác */
    public function expireStalePending(): int
    {
        $rows = $this->db->query(
            "SELECT id FROM bookings
             WHERE status = 'PENDING' AND pending_expires_at IS NOT NULL
               AND pending_expires_at < NOW() AND deleted_at IS NULL
             LIMIT 200"
        );
        foreach ($rows as $r) {
            $this->changeStatus((int)$r['id'], 'EXPIRED', 0, 'Hết hạn giữ chỗ tự động');
        }
        return count($rows);
    }

    // --------------------------------------------------------
    //  4. TRUY VẤN
    // --------------------------------------------------------

    public function findByCustomer(int $customerId, int $page = 1, int $perPage = 10): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $data = $this->db->query(
            "SELECT bk.*, br.name AS branch_name, bu.name AS business_name, bu.logo
             FROM bookings bk
             JOIN branches   br ON br.id = bk.branch_id
             JOIN businesses bu ON bu.id = br.business_id
             WHERE bk.customer_id = ? AND bk.deleted_at IS NULL
             ORDER BY bk.appointment_date DESC, bk.appointment_start_time DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$customerId]
        );

        $total = $this->db->count('bookings', 'customer_id = ? AND deleted_at IS NULL', [$customerId]);
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'pages' => (int)ceil($total / $perPage)];
    }

    public function findDetail(int $id): array|false
    {
        $booking = $this->db->queryOne(
            "SELECT bk.*, br.name AS branch_name, br.address_line, br.phone AS branch_phone,
                    br.business_id,
                    bu.name AS business_name, bu.logo,
                    bc.full_name AS contact_name, bc.phone AS contact_phone
             FROM bookings bk
             JOIN branches   br ON br.id = bk.branch_id
             JOIN businesses bu ON bu.id = br.business_id
             -- chỉ lấy 1 dòng contact để không nhân bản booking
             LEFT JOIN booking_contacts bc
                    ON bc.id = (SELECT id FROM booking_contacts c2
                                WHERE c2.booking_id = bk.id ORDER BY c2.id LIMIT 1)
             WHERE bk.id = ? AND bk.deleted_at IS NULL LIMIT 1",
            [$id]
        );
        if (!$booking) return false;

        $booking['services'] = $this->db->query(
            "SELECT bs.*, sp.full_name AS staff_name, sp.avatar AS staff_avatar
             FROM booking_services bs
             LEFT JOIN staff_profiles sp ON sp.id = bs.staff_id
             WHERE bs.booking_id = ?
             ORDER BY bs.sort_order",
            [$id]
        );
        return $booking;
    }

    /**
     * FIX: bản cũ truyền $where chứa alias "bk." vào db->count('bookings', ...)
     *      → sinh câu "SELECT COUNT(*) FROM bookings WHERE bk.branch_id = ?"
     *      → MySQL báo Unknown column 'bk.branch_id' → trang /salon/bookings sập.
     */
    public function findByBranch(int $branchId, int $page = 1, int $perPage = 20, string $status = '', string $date = ''): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where  = "bk.branch_id = ? AND bk.deleted_at IS NULL";
        $params = [$branchId];

        // Chỉ chấp nhận status nằm trong danh sách hợp lệ (chống bơm giá trị lạ)
        $allowed = array_keys(self::TRANSITIONS);
        if ($status !== '' && in_array($status, $allowed, true)) {
            $where .= " AND bk.status = ?"; $params[] = $status;
        }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $where .= " AND bk.appointment_date = ?"; $params[] = $date;
        }

        $offset = ($page - 1) * $perPage;

        $data = $this->db->query(
            "SELECT bk.*, u.full_name AS customer_name, bc.full_name AS guest_name, bc.phone AS guest_phone
             FROM bookings bk
             LEFT JOIN customer_profiles cp ON cp.id = bk.customer_id
             LEFT JOIN users u  ON u.id  = cp.user_id
             LEFT JOIN booking_contacts bc
                    ON bc.id = (SELECT id FROM booking_contacts c2
                                WHERE c2.booking_id = bk.id ORDER BY c2.id LIMIT 1)
             WHERE {$where}
             ORDER BY bk.appointment_date DESC, bk.appointment_start_time DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Câu đếm dùng CHÍNH alias bk → không còn lỗi Unknown column
        $total = (int)($this->db->queryOne(
            "SELECT COUNT(*) AS cnt FROM bookings bk WHERE {$where}", $params
        )['cnt'] ?? 0);

        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'pages' => (int)ceil($total / $perPage)];
    }

    /** Kiểm tra 1 booking có thuộc chi nhánh này không — dùng cho RBAC */
    public function belongsToBranch(int $bookingId, int $branchId): bool
    {
        return (bool)$this->db->queryOne(
            "SELECT id FROM bookings WHERE id = ? AND branch_id = ? AND deleted_at IS NULL LIMIT 1",
            [$bookingId, $branchId]
        );
    }

    public function statsForBranch(int $branchId): array
    {
        $today = date('Y-m-d');
        return [
            'today_total'     => $this->db->count('bookings', "branch_id = ? AND appointment_date = ? AND deleted_at IS NULL", [$branchId, $today]),
            'pending_count'   => $this->db->count('bookings', "branch_id = ? AND status = 'PENDING' AND deleted_at IS NULL", [$branchId]),
            'confirmed_count' => $this->db->count('bookings', "branch_id = ? AND status = 'CONFIRMED' AND deleted_at IS NULL", [$branchId]),
            'month_revenue'   => $this->db->queryOne(
                "SELECT COALESCE(SUM(final_amount),0) AS total FROM bookings
                 WHERE branch_id = ? AND status = 'COMPLETED'
                   AND appointment_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
                   AND appointment_date <  DATE_FORMAT(NOW() + INTERVAL 1 MONTH,'%Y-%m-01')
                   AND deleted_at IS NULL",
                [$branchId]
            )['total'] ?? 0,
        ];
    }
}

/** Ngoại lệ riêng cho trùng lịch — để Controller phân biệt với lỗi hệ thống */
class BookingConflictException extends \RuntimeException {}
