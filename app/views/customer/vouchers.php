<!-- app/views/customer/vouchers.php — Danh sách voucher của khách hàng -->
<div class="page-header">
  <div>
    <h1>🎁 Kho voucher của tôi</h1>
    <p style="color:var(--text-light)">Các mã ưu đãi bạn đang sở hữu để áp dụng khi đặt lịch</p>
  </div>
</div>

<div class="card">
  <?php if (empty($vouchers)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-light)">
      <div style="font-size:3rem;margin-bottom:12px">🏷️</div>
      <h3>Bạn chưa có mã giảm giá nào</h3>
      <p style="margin-bottom:16px">Hãy theo dõi các salon để nhận những ưu đãi hấp dẫn!</p>
      <a href="/search" class="btn btn-primary">Tìm salon có khuyến mãi</a>
    </div>
  <?php else: ?>
    <div class="grid-2">
      <?php foreach ($vouchers as $v): ?>
        <div style="border:2px dashed var(--primary);border-radius:var(--radius);padding:16px;display:flex;justify-content:space-between;align-items:center;background:#fff5f9">
          <div>
            <div style="font-size:0.8rem;color:var(--primary);font-weight:700;text-transform:uppercase">MÃ GIẢM GIÁ</div>
            <h3 style="margin:4px 0"><?= e($v['name']) ?></h3>
            <div style="font-size:1.1rem;font-weight:700;color:var(--primary)">
              <?= $v['discount_type'] === 'PERCENTAGE' ? 'Giảm ' . (int)$v['discount_value'] . '%' : 'Giảm ' . formatMoney($v['discount_value']) ?>
            </div>
            <div style="font-size:0.8rem;color:var(--text-light);margin-top:4px">
              HSD: <?= formatDateOnly($v['end_date']) ?>
            </div>
          </div>
          <div style="text-align:center">
            <span style="display:inline-block;padding:6px 14px;background:var(--primary);color:#fff;border-radius:4px;font-weight:700;letter-spacing:1px">
              <?= e($v['code']) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
