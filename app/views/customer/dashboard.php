<!-- app/views/customer/dashboard.php -->
<?php
$dayOfWeek = ['Chủ nhật','Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7'];
?>

<div class="page-header">
  <h1>Xin chào, <?= e(Session::get('user_name')) ?>! 👋</h1>
  <a href="/search" class="btn btn-primary">+ Đặt lịch mới</a>
</div>

<!-- Thống kê -->
<div class="stats-grid" style="margin-bottom:28px">
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Tổng lịch hẹn</div>
    <div class="stat-value"><?= $stats['total'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Đã hoàn thành</div>
    <div class="stat-value" style="color:var(--success)"><?= $stats['completed'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-label">Sắp tới</div>
    <div class="stat-value" style="color:var(--warning)"><?= $stats['pending'] ?></div>
  </div>
  <div class="stat-card">
    <a href="/customer/vouchers" style="text-decoration:none;color:inherit;display:block">
      <div class="stat-icon">🎁</div>
      <div class="stat-label">Voucher của tôi</div>
      <div class="stat-value" style="color:var(--primary)">Xem →</div>
    </a>
  </div>
</div>

<!-- Lịch hẹn gần đây -->
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span>📋 Lịch hẹn gần đây</span>
    <a href="/customer/bookings" style="font-size:0.9rem">Xem tất cả →</a>
  </div>

  <?php if (empty($recentBookings)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-light)">
      <p style="font-size:2.5rem">📆</p>
      <p>Bạn chưa có lịch hẹn nào.</p>
      <a href="/search" class="btn btn-primary" style="margin-top:12px">Đặt lịch ngay</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mã lịch</th>
            <th>Salon</th>
            <th>Ngày hẹn</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentBookings as $bk): ?>
            <tr>
              <td><strong><?= e($bk['booking_code']) ?></strong></td>
              <td><?= e($bk['business_name']) ?><br><small style="color:var(--text-light)"><?= e($bk['branch_name']) ?></small></td>
              <td><?= formatDateOnly($bk['appointment_date']) ?></td>
              <td><?= substr($bk['appointment_start_time'], 0, 5) ?></td>
              <td><?= formatMoney($bk['final_amount'] ?? $bk['total_amount']) ?></td>
              <td><?= bookingStatusLabel($bk['status']) ?></td>
              <td><a href="/customer/bookings/<?= $bk['id'] ?>" class="btn btn-white btn-sm">Xem</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
