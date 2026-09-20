<?php
// ============================================================
//  app/controllers/AvailabilityController.php  — FILE MỚI
//
//  Endpoint kiểm tra khung giờ còn trống, phục vụ booking-form.js.
//  Thêm vào config/routes.php, mục 'GET':
//      '/api/availability' => ['AvailabilityController', 'check'],
//      '/api/slots'        => ['AvailabilityController', 'slots'],
//
//  Lưu ý: endpoint này CHỈ để cải thiện trải nghiệm. Việc chống trùng
//  lịch thật sự nằm ở BookingModel::createFull() (có khoá transaction).
//  Không bao giờ tin vào kết quả kiểm tra phía client.
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/BookingModel.php';
require_once BASE_PATH . '/app/models/ServiceModel.php';

class AvailabilityController extends Controller
{
    private BookingModel $bookings;

    public function __construct()
    {
        $this->bookings = new BookingModel();
    }

    /** GET /api/availability?branch_id=&date=&start=&staff_id=&service_ids[]= */
    public function check(): void
    {
        $branchId = (int)$this->query('branch_id', 0);
        $date     = (string)$this->query('date');
        $start    = (string)$this->query('start');
        $staffId  = (int)$this->query('staff_id', 0);

        $rawIds = $_GET['service_ids'] ?? [];
        if (!is_array($rawIds)) $rawIds = [$rawIds];
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $rawIds))));

        if ($branchId <= 0 || !isValidDate($date) || !isValidTime($start) || empty($serviceIds)) {
            $this->json(['available' => false, 'message' => 'Thiếu thông tin để kiểm tra.'], 400);
        }

        $start = normalizeTime($start);

        // Tính thời lượng từ DB, không tin tham số client
        $svcModel = new ServiceModel();
        $duration = 0;
        foreach ($serviceIds as $sid) {
            $svc = $svcModel->findForBranch($sid, $branchId);
            if (!$svc) {
                $this->json(['available' => false, 'message' => 'Dịch vụ không hợp lệ.']);
            }
            $duration += (int)$svc['duration_minutes'];
        }

        $endSec = timeToSeconds($start) + $duration * 60;
        if ($endSec > 24 * 3600) {
            $this->json([
                'available' => false,
                'message'   => 'Tổng thời lượng ' . formatDuration($duration) . ' vượt quá giờ đóng cửa trong ngày.',
            ]);
        }
        $end = secondsToTime($endSec);

        $policy = $this->bookings->policyFor($branchId);
        $buffer = (int)($policy['default_buffer_minutes'] ?? 0);

        if (!$this->bookings->isWithinWorkingHours($branchId, $date, $start, $end)) {
            $this->json([
                'available' => false,
                'message'   => 'Chi nhánh không mở cửa trong khung giờ này.',
                'end_time'  => substr($end, 0, 5),
            ]);
        }

        if (strtotime("$date $start") < time() + (int)$policy['lead_time_minutes'] * 60) {
            $this->json([
                'available' => false,
                'message'   => 'Cần đặt trước ít nhất ' . formatDuration((int)$policy['lead_time_minutes']) . '.',
            ]);
        }

        if ($staffId > 0) {
            $conflict = $this->bookings->findConflict($branchId, $date, $start, $end, $staffId, $buffer);
            $this->json([
                'available' => !$conflict,
                'message'   => $conflict ? 'Nhân viên bạn chọn đã có lịch trong khung giờ này.' : '',
                'end_time'  => substr($end, 0, 5),
                'duration'  => $duration,
            ]);
        }

        $free = $this->bookings->countFreeStaff($branchId, $date, $start, $end, $buffer);
        $this->json([
            'available'  => $free > 0,
            'free_staff' => $free,
            'message'    => $free > 0 ? '' : 'Chi nhánh đã kín lịch vào khung giờ này.',
            'end_time'   => substr($end, 0, 5),
            'duration'   => $duration,
        ]);
    }

    /** GET /api/slots?branch_id=&date=&service_ids[]= → danh sách khung giờ 30 phút */
    public function slots(): void
    {
        $branchId = (int)$this->query('branch_id', 0);
        $date     = (string)$this->query('date');
        $staffId  = (int)$this->query('staff_id', 0);

        $rawIds = $_GET['service_ids'] ?? [];
        if (!is_array($rawIds)) $rawIds = [$rawIds];
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $rawIds))));

        if ($branchId <= 0 || !isValidDate($date) || empty($serviceIds)) {
            $this->json(['slots' => []], 400);
        }

        $svcModel = new ServiceModel();
        $duration = 0;
        foreach ($serviceIds as $sid) {
            $svc = $svcModel->findForBranch($sid, $branchId);
            if ($svc) $duration += (int)$svc['duration_minutes'];
        }
        if ($duration <= 0) $this->json(['slots' => []]);

        $db  = \Database::getInstance();
        $dow = (int)date('w', strtotime($date));
        $wh  = $db->queryOne(
            "SELECT open_time, close_time, is_closed FROM branch_working_hours
             WHERE branch_id = ? AND day_of_week = ? LIMIT 1",
            [$branchId, $dow]
        );
        if (!$wh || (int)$wh['is_closed'] === 1) {
            $this->json(['slots' => [], 'closed' => true]);
        }

        $policy  = $this->bookings->policyFor($branchId);
        $buffer  = (int)($policy['default_buffer_minutes'] ?? 0);
        $leadTs  = time() + (int)$policy['lead_time_minutes'] * 60;

        $slots   = [];
        $step    = 30 * 60;
        $openSec = timeToSeconds($wh['open_time']);
        $closeSec= timeToSeconds($wh['close_time']);

        for ($s = $openSec; $s + $duration * 60 <= $closeSec; $s += $step) {
            $start = secondsToTime($s);
            $end   = secondsToTime($s + $duration * 60);

            if (strtotime("$date $start") < $leadTs) {
                $slots[] = ['time' => substr($start, 0, 5), 'available' => false, 'reason' => 'past'];
                continue;
            }

            $free = $staffId > 0
                ? (!$this->bookings->findConflict($branchId, $date, $start, $end, $staffId, $buffer) ? 1 : 0)
                : $this->bookings->countFreeStaff($branchId, $date, $start, $end, $buffer);

            $slots[] = [
                'time'      => substr($start, 0, 5),
                'end'       => substr($end, 0, 5),
                'available' => $free > 0,
                'free'      => $free,
            ];
        }

        $this->json(['slots' => $slots, 'duration' => $duration]);
    }
}
