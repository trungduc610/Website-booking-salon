<!-- app/views/salon/promotions.php — Quản lý khuyến mãi & voucher của salon -->
<div class="page-header">
  <div>
    <h1>🎁 Khuyến mãi & Voucher</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <div class="card">
      <div class="card-header">Danh sách Voucher của Salon</div>

      <?php if (empty($vouchers)): ?>
        <div style="text-align:center;padding:48px;color:var(--text-light)">
          <div style="font-size:3rem;margin-bottom:8px">🏷️</div>
          <p>Chưa có chương trình khuyến mãi nào được tạo.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên chương trình</th>
                <th>Mức giảm</th>
                <th>Thời hạn</th>
                <th>Đã dùng / Tổng số</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vouchers as $v): ?>
                <tr>
                  <td>
                    <span style="padding:4px 8px;background:var(--bg);border:1px dashed var(--primary);color:var(--primary);border-radius:4px;font-weight:700">
                      <?= e($v['code']) ?>
                    </span>
                  </td>
                  <td><strong><?= e($v['name']) ?></strong></td>
                  <td>
                    <?= $v['discount_type'] === 'PERCENTAGE' ? (int)$v['discount_value'] . '%' : formatMoney($v['discount_value']) ?>
                  </td>
                  <td><?= formatDateOnly($v['start_date']) ?> - <?= formatDateOnly($v['end_date']) ?></td>
                  <td><?= $v['used_quantity'] ?> / <?= $v['total_quantity'] ?: '∞' ?></td>
                  <td>
                    <?= $v['status'] === 'ACTIVE' ? '<span class="badge badge-success">Đang chạy</span>' : '<span class="badge badge-gray">Hết hạn</span>' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
