<!-- app/views/admin/reviews.php — Kiểm duyệt đánh giá -->
<div class="page-header">
  <div>
    <h1>⭐ Kiểm duyệt đánh giá</h1>
    <p style="color:var(--text-light)">Theo dõi các đánh giá của khách hàng đối với các salon</p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <div class="card">
      <div class="card-header">Tất cả đánh giá mới nhất (<?= count($reviews) ?>)</div>

      <?php if (empty($reviews)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <p>Chưa có đánh giá nào trên hệ thống.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Khách hàng</th>
                <th>Salon</th>
                <th>Số sao</th>
                <th>Nội dung nhận xét</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviews as $rv): ?>
                <tr>
                  <td><strong><?= e($rv['customer_name'] ?? 'Khách ẩn danh') ?></strong></td>
                  <td><?= e($rv['business_name']) ?></td>
                  <td><?= renderStars($rv['overall_rating']) ?></td>
                  <td><?= nl2br(e($rv['comment'] ?? '—')) ?></td>
                  <td><?= formatDate($rv['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
