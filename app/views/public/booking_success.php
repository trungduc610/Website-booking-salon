<!-- app/views/public/booking_success.php — Thông báo đặt lịch thành công -->
<div style="max-width:580px;margin:40px auto;text-align:center">
  <div class="card" style="padding:40px 30px">
    <div style="font-size:3.5rem;color:var(--success);margin-bottom:12px">🎉</div>
    <h1 style="font-size:1.8rem;font-weight:700;margin-bottom:8px">Đặt lịch thành công!</h1>
    <p style="color:var(--text-light);margin-bottom:24px">
      Cảm ơn bạn đã tin tưởng dịch vụ. Yêu cầu đặt lịch của bạn đã được gửi tới salon để xác nhận.
    </p>

    <?php if ($booking): ?>
      <div style="background:var(--bg);border-radius:var(--radius);padding:20px;text-align:left;margin-bottom:24px;border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--border);padding-bottom:10px;margin-bottom:10px">
          <span style="color:var(--text-light)">Mã lịch hẹn:</span>
          <strong style="color:var(--primary);font-size:1.1rem"><?= e($booking['booking_code']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
          <span style="color:var(--text-light)">Salon:</span>
          <strong><?= e($booking['business_name']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
          <span style="color:var(--text-light)">Chi nhánh:</span>
          <span><?= e($booking['branch_name']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
          <span style="color:var(--text-light)">Thời gian:</span>
          <strong><?= substr($booking['appointment_start_time'], 0, 5) ?>, <?= formatDateOnly($booking['appointment_date']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
          <span style="color:var(--text-light)">Tổng tiền dự kiến:</span>
          <strong style="color:var(--primary)"><?= formatMoney($booking['final_amount'] ?? $booking['total_amount']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-light)">Trạng thái:</span>
          <span><?= bookingStatusLabel($booking['status']) ?></span>
        </div>
      </div>
    <?php else: ?>
      <div style="margin-bottom:24px">
        <p>Mã đặt chỗ của bạn: <strong><?= e($code) ?></strong></p>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;justify-content:center">
      <a href="/" class="btn btn-outline">← Về trang chủ</a>
      <a href="/search" class="btn btn-primary">Khám phá salon khác</a>
    </div>
  </div>
</div>
