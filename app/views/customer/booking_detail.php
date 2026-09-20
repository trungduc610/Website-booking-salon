<!-- app/views/customer/booking_detail.php — Chi tiết lịch hẹn -->
<div style="max-width:800px;margin:0 auto">

  <div class="page-header">
    <div>
      <a href="/customer/bookings" style="color:var(--text-light);font-size:0.9rem">← Quay lại danh sách</a>
      <h1 style="font-size:1.6rem;font-weight:700;margin-top:6px">Lịch hẹn #<?= e($booking['booking_code']) ?></h1>
    </div>
    <div>
      <?= bookingStatusLabel($booking['status']) ?>
    </div>
  </div>

  <!-- Thông tin salon & địa điểm -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">🏪 Thông tin salon</div>
    <div style="display:flex;gap:16px;align-items:center">
      <?php if (!empty($booking['logo'])): ?>
        <img src="<?= uploadUrl($booking['logo']) ?>" style="width:64px;height:64px;border-radius:10px;object-fit:cover;border:1px solid var(--border)">
      <?php else: ?>
        <div style="width:64px;height:64px;background:var(--primary);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.8rem">💅</div>
      <?php endif; ?>
      <div>
        <h3 style="margin-bottom:4px"><?= e($booking['business_name']) ?></h3>
        <p style="color:var(--text-light);font-size:0.9rem;margin:0">📍 <?= e($booking['branch_name']) ?> - <?= e($booking['address_line'] ?? '') ?></p>
        <?php if (!empty($booking['branch_phone'])): ?>
          <p style="color:var(--text-light);font-size:0.9rem;margin:0">📞 Hotline: <?= e($booking['branch_phone']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Chi tiết thời gian & dịch vụ -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">✂️ Dịch vụ đã đặt</div>
    <div style="margin-bottom:16px;background:var(--bg);padding:14px;border-radius:var(--radius)">
      <strong>📅 Thời gian:</strong> <?= substr($booking['appointment_start_time'], 0, 5) ?> - <?= substr($booking['appointment_end_time'], 0, 5) ?>, ngày <?= formatDateOnly($booking['appointment_date']) ?>
      <?php if (!empty($booking['note'])): ?>
        <div style="margin-top:6px;font-size:0.9rem;color:var(--text-light)">
          <strong>Ghi chú:</strong> <?= e($booking['note']) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Dịch vụ</th>
            <th>Thời lượng</th>
            <th>Nhân viên</th>
            <th style="text-align:right">Giá</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($booking['services'] as $svc): ?>
            <tr>
              <td><strong><?= e($svc['service_name_snapshot']) ?></strong></td>
              <td>⏱ <?= formatDuration($svc['duration_minutes']) ?></td>
              <td><?= e($svc['staff_name'] ?? 'Bất kỳ') ?></td>
              <td style="text-align:right"><?= formatMoney($svc['price_at_booking']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" style="text-align:right">Tổng cộng:</th>
            <th style="text-align:right;color:var(--primary);font-size:1.1rem">
              <?= formatMoney($booking['final_amount'] ?? $booking['total_amount']) ?>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Nút hủy booking nếu còn đang chờ / xác nhận -->
  <?php if (in_array($booking['status'], ['PENDING', 'CONFIRMED'])): ?>
    <div class="card" style="margin-bottom:20px;border-color:var(--danger)">
      <div class="card-header" style="color:var(--danger)">⚠️ Hủy lịch hẹn</div>
      <p style="color:var(--text-light);font-size:0.9rem;margin-bottom:14px">
        Nếu có việc bận đột xuất, bạn có thể hủy lịch hẹn trước thời gian quy định.
      </p>
      <form action="/customer/bookings/<?= $booking['id'] ?>/cancel" method="POST">
        <?= Session::csrfField() ?>
        <div class="form-group">
          <input type="text" name="reason" class="form-control" placeholder="Lý do hủy (không bắt buộc)">
        </div>
        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Bạn có chắc chắn muốn hủy lịch hẹn này?">
          Xác nhận hủy lịch
        </button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Đánh giá nếu booking đã hoàn thành -->
  <?php if ($booking['status'] === 'COMPLETED'): ?>
    <?php
      $db = \Database::getInstance();
      $existingReview = $db->queryOne("SELECT * FROM reviews WHERE booking_id = ? LIMIT 1", [$booking['id']]);
    ?>
    <div class="card">
      <div class="card-header">⭐ Đánh giá trải nghiệm</div>
      <?php if ($existingReview): ?>
        <div style="background:var(--bg);padding:16px;border-radius:var(--radius)">
          <div style="margin-bottom:6px">
            <?= renderStars($existingReview['overall_rating']) ?>
            <span style="color:var(--text-light);font-size:0.85rem;margin-left:8px"><?= formatDate($existingReview['created_at']) ?></span>
          </div>
          <p style="margin:0"><?= nl2br(e($existingReview['comment'] ?? 'Không có bình luận thêm.')) ?></p>
        </div>
      <?php else: ?>
        <form action="/reviews/create" method="POST">
          <?= Session::csrfField() ?>
          <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

          <div class="form-group">
            <label class="form-label">Mức độ hài lòng</label>
            <select name="overall_rating" class="form-control" style="max-width:200px">
              <option value="5">⭐⭐⭐⭐⭐ Tuyệt vời (5 sao)</option>
              <option value="4">⭐⭐⭐⭐ Rất tốt (4 sao)</option>
              <option value="3">⭐⭐⭐ Bình thường (3 sao)</option>
              <option value="2">⭐⭐ Chưa hài lòng (2 sao)</option>
              <option value="1">⭐ Tệ (1 sao)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Chia sẻ cảm nhận của bạn</label>
            <textarea name="comment" class="form-control" rows="3" placeholder="Nhân viên nhiệt tình, không gian sạch sẽ..."></textarea>
          </div>

          <div class="form-group" style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="is_anonymous" id="is_anon" value="1">
            <label for="is_anon" style="font-size:0.9rem;cursor:pointer">Đánh giá ẩn danh (không hiện tên công khai)</label>
          </div>

          <button type="submit" class="btn btn-primary">Gửi đánh giá</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
