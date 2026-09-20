<!-- app/views/salon/bookings.php — Quản lý lịch hẹn của salon -->
<div class="page-header">
  <div>
    <h1>📋 Quản lý lịch hẹn</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Bộ lọc -->
    <div class="card" style="margin-bottom:20px">
      <form method="GET" action="/salon/bookings" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;min-width:160px">
          <label class="form-label">Ngày hẹn</label>
          <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
        </div>
        <div class="form-group" style="margin:0;min-width:180px">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-control">
            <option value="">Tất cả trạng thái</option>
            <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Chờ xác nhận</option>
            <option value="CONFIRMED" <?= $status === 'CONFIRMED' ? 'selected' : '' ?>>Đã xác nhận</option>
            <option value="COMPLETED" <?= $status === 'COMPLETED' ? 'selected' : '' ?>>Đã hoàn thành</option>
            <option value="CANCELLED" <?= $status === 'CANCELLED' ? 'selected' : '' ?>>Đã hủy</option>
            <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Từ chối</option>
          </select>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary">Lọc</button>
          <a href="/salon/bookings" class="btn btn-white">Đặt lại</a>
        </div>
      </form>
    </div>

    <!-- Bảng danh sách -->
    <div class="card">
      <?php if (empty($result['data'])): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <div style="font-size:2.5rem;margin-bottom:8px">📅</div>
          <p>Không tìm thấy lịch hẹn nào theo điều kiện lọc.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mã</th>
                <th>Khách hàng</th>
                <th>Ngày & Giờ</th>
                <th>Tổng tiền</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result['data'] as $bk): ?>
                <?php $custName = $bk['customer_name'] ?? $bk['guest_name'] ?? 'Khách vãng lai'; ?>
                <tr>
                  <td><strong><?= e($bk['booking_code']) ?></strong></td>
                  <td>
                    <div style="font-weight:600"><?= e($custName) ?></div>
                    <?php if (!empty($bk['guest_phone'])): ?>
                      <small style="color:var(--text-light)">📞 <?= e($bk['guest_phone']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= formatDateOnly($bk['appointment_date']) ?><br>
                    <small style="color:var(--text-light)">⏱ <?= substr($bk['appointment_start_time'], 0, 5) ?> - <?= substr($bk['appointment_end_time'], 0, 5) ?></small>
                  </td>
                  <td><strong style="color:var(--primary)"><?= formatMoney($bk['final_amount'] ?? $bk['total_amount']) ?></strong></td>
                  <td><?= bookingStatusLabel($bk['status']) ?></td>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <?php if ($bk['status'] === 'PENDING'): ?>
                        <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/confirm" style="display:inline">
                          <?= Session::csrfField() ?>
                          <button class="btn btn-success btn-sm">✓ Xác nhận</button>
                        </form>
                        <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/reject" style="display:inline">
                          <?= Session::csrfField() ?>
                          <input type="hidden" name="reason" value="Salon bận / từ chối">
                          <button class="btn btn-danger btn-sm" data-confirm="Từ chối lịch hẹn này?">✕ Từ chối</button>
                        </form>
                      <?php elseif ($bk['status'] === 'CONFIRMED'): ?>
                        <form method="POST" action="/salon/bookings/<?= $bk['id'] ?>/complete" style="display:inline">
                          <?= Session::csrfField() ?>
                          <button class="btn btn-primary btn-sm">✔ Hoàn thành</button>
                        </form>
                      <?php else: ?>
                        <span style="color:var(--text-light);font-size:0.85rem">—</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php $pUrl = '/salon/bookings?date=' . urlencode($date) . '&status=' . urlencode($status); ?>
        <?= renderPagination($result, $pUrl) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
