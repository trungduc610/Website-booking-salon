<!-- app/views/salon/dashboard.php — Dashboard quản lý salon -->

<div class="page-header">
  <div>
    <h1>📊 Dashboard</h1>
    <p style="color:var(--text-light);margin-top:2px"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
  <a href="/salon/bookings" class="btn btn-primary">Quản lý lịch hẹn</a>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Lịch hẹn hôm nay</div>
    <div class="stat-value"><?= $stats['today_total'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-label">Chờ xác nhận</div>
    <div class="stat-value" style="color:var(--warning)"><?= $stats['pending_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Đã xác nhận</div>
    <div class="stat-value" style="color:var(--success)"><?= $stats['confirmed_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Doanh thu tháng này</div>
    <div class="stat-value" style="color:var(--primary);font-size:1.2rem"><?= formatMoney($stats['month_revenue']) ?></div>
  </div>
</div>

<!-- Menu nhanh -->
<div class="grid-4" style="margin-bottom:24px">
  <a href="/salon/bookings" class="card" style="text-align:center;text-decoration:none;color:inherit;padding:20px">
    <div style="font-size:2rem;margin-bottom:8px">📋</div>
    <div style="font-weight:600">Lịch hẹn</div>
  </a>
  <a href="/salon/services" class="card" style="text-align:center;text-decoration:none;color:inherit;padding:20px">
    <div style="font-size:2rem;margin-bottom:8px">✂️</div>
    <div style="font-weight:600">Dịch vụ</div>
  </a>
  <a href="/salon/staff" class="card" style="text-align:center;text-decoration:none;color:inherit;padding:20px">
    <div style="font-size:2rem;margin-bottom:8px">👥</div>
    <div style="font-weight:600">Nhân viên</div>
  </a>
  <a href="/salon/reviews" class="card" style="text-align:center;text-decoration:none;color:inherit;padding:20px">
    <div style="font-size:2rem;margin-bottom:8px">⭐</div>
    <div style="font-weight:600">Đánh giá</div>
  </a>
</div>

<!-- Lịch hẹn hôm nay -->
<div class="card">
  <div class="card-header">
    📆 Lịch hẹn hôm nay — <?= formatDateOnly(date('Y-m-d')) ?>
  </div>

  <?php if (empty($todayBookings)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-light)">
      <p style="font-size:2rem">🌟</p>
      <p>Không có lịch hẹn nào hôm nay.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mã</th>
            <th>Khách</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($todayBookings as $bk): ?>
            <?php $name = $bk['customer_name'] ?? $bk['guest_name'] ?? 'Khách vãng lai'; ?>
            <tr>
              <td><strong><?= e($bk['booking_code']) ?></strong></td>
              <td><?= e($name) ?></td>
              <td><?= substr($bk['appointment_start_time'],0,5) ?> – <?= substr($bk['appointment_end_time'],0,5) ?></td>
              <td><?= formatMoney($bk['final_amount'] ?? $bk['total_amount']) ?></td>
              <td><?= bookingStatusLabel($bk['status']) ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if ($bk['status'] === 'PENDING'): ?>
                  <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/confirm" style="display:inline">
                    <?= Session::csrfField() ?>
                    <button class="btn btn-success btn-sm">✓ Xác nhận</button>
                  </form>
                  <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/reject" style="display:inline">
                    <?= Session::csrfField() ?>
                    <input type="hidden" name="reason" value="Salon từ chối">
                    <button class="btn btn-danger btn-sm" data-confirm="Từ chối lịch hẹn này?">✕ Từ chối</button>
                  </form>
                <?php elseif ($bk['status'] === 'CONFIRMED'): ?>
                  <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/complete" style="display:inline">
                    <?= Session::csrfField() ?>
                    <button class="btn btn-primary btn-sm">✔ Hoàn thành</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
