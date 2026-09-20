<!-- app/views/customer/bookings.php — Danh sách lịch hẹn của khách hàng -->
<div class="page-header">
  <div>
    <h1>📋 Lịch hẹn của tôi</h1>
    <p style="color:var(--text-light)">Theo dõi tiến độ và lịch sử các lần đặt lịch làm đẹp</p>
  </div>
  <a href="/search" class="btn btn-primary">+ Đặt lịch mới</a>
</div>

<div class="card">
  <?php if (empty($result['data'])): ?>
    <div style="text-align:center;padding:48px;color:var(--text-light)">
      <div style="font-size:3rem;margin-bottom:12px">📅</div>
      <h3>Bạn chưa có lịch hẹn nào</h3>
      <p style="margin-bottom:16px">Hãy chọn salon yêu thích và đặt lịch ngay hôm nay!</p>
      <a href="/search" class="btn btn-primary">Tìm salon ngay</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mã lịch</th>
            <th>Salon & Chi nhánh</th>
            <th>Ngày hẹn</th>
            <th>Giờ</th>
            <th>Tổng tiền</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $bk): ?>
            <tr>
              <td><strong><?= e($bk['booking_code']) ?></strong></td>
              <td>
                <div style="font-weight:600"><?= e($bk['business_name']) ?></div>
                <small style="color:var(--text-light)">📍 <?= e($bk['branch_name']) ?></small>
              </td>
              <td><?= formatDateOnly($bk['appointment_date']) ?></td>
              <td><?= substr($bk['appointment_start_time'], 0, 5) ?></td>
              <td><strong style="color:var(--primary)"><?= formatMoney($bk['final_amount'] ?? $bk['total_amount']) ?></strong></td>
              <td><?= bookingStatusLabel($bk['status']) ?></td>
              <td>
                <a href="/customer/bookings/<?= $bk['id'] ?>" class="btn btn-white btn-sm">Chi tiết</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?= renderPagination($result, '/customer/bookings') ?>
  <?php endif; ?>
</div>
