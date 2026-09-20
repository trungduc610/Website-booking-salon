<!-- app/views/salon/reviews.php — Đánh giá của khách hàng về salon -->
<div class="page-header">
  <div>
    <h1>⭐ Đánh giá từ khách hàng</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <div class="card">
      <div class="card-header">Nhận xét & Đánh giá (<?= count($reviews) ?>)</div>

      <?php if (empty($reviews)): ?>
        <div style="text-align:center;padding:48px;color:var(--text-light)">
          <div style="font-size:3rem;margin-bottom:8px">💬</div>
          <p>Chưa có đánh giá nào từ khách hàng.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $rv): ?>
          <div style="padding:16px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
              <div>
                <strong><?= e($rv['customer_name'] ?? 'Khách hàng') ?></strong>
                <span style="color:var(--text-light);font-size:0.85rem;margin-left:8px">
                  <?= formatDate($rv['created_at']) ?>
                </span>
              </div>
              <div><?= renderStars($rv['overall_rating']) ?></div>
            </div>
            <p style="margin:0;color:var(--text)"><?= nl2br(e($rv['comment'] ?? '')) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
