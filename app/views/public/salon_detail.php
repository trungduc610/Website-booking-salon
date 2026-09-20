<!-- app/views/public/salon_detail.php — Chi tiết salon -->

<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">

  <!-- === CỘT TRÁI: thông tin salon === -->
  <div style="flex:2;min-width:320px">

    <!-- Ảnh bìa -->
    <div style="height:300px;background:linear-gradient(135deg,#fce4f3,#e8f4fd);border-radius:12px;overflow:hidden;margin-bottom:24px;display:flex;align-items:center;justify-content:center">
      <?php if (!empty($photos)): ?>
        <img src="<?= uploadUrl($photos[0]['url']) ?>" alt="<?= e($salon['name']) ?>" style="width:100%;height:100%;object-fit:cover">
      <?php elseif ($salon['logo']): ?>
        <img src="<?= uploadUrl($salon['logo']) ?>" alt="" style="max-height:200px;max-width:80%">
      <?php else: ?>
        <span style="font-size:5rem">💅</span>
      <?php endif; ?>
    </div>

    <!-- Tên + rating -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <h1 style="font-size:1.8rem;font-weight:700;margin-bottom:6px"><?= e($salon['name']) ?></h1>
          <?= renderStars((int)round($salon['avg_rating'])) ?>
          <span style="color:var(--text-light);margin-left:6px"><?= number_format($salon['avg_rating'],1) ?> / 5 (<?= $salon['review_count'] ?> đánh giá)</span>
        </div>
        <?php if ($branches): ?>
          <a href="/book?branch_id=<?= $branches[0]['id'] ?>" class="btn btn-primary btn-lg">
            Đặt lịch ngay
          </a>
        <?php endif; ?>
      </div>
      <?php if ($salon['description']): ?>
        <p style="margin-top:16px;color:var(--text-light)"><?= nl2br(e($salon['description'])) ?></p>
      <?php endif; ?>
    </div>

    <!-- Chi nhánh -->
    <?php if ($branches): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">📍 Chi nhánh</div>
      <?php foreach ($branches as $br): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border)">
          <strong><?= e($br['name']) ?></strong>
          <?php if ($br['address_line']): ?>
            <div style="color:var(--text-light);font-size:0.9rem"><?= e($br['address_line']) ?></div>
          <?php endif; ?>
          <?php if ($br['phone']): ?>
            <div style="color:var(--text-light);font-size:0.9rem">📞 <?= e($br['phone']) ?></div>
          <?php endif; ?>
          <div style="margin-top:8px">
            <a href="/book?branch_id=<?= $br['id'] ?>" class="btn btn-outline btn-sm">Đặt tại chi nhánh này</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Danh sách dịch vụ -->
    <?php if (!empty($services)): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">✂️ Dịch vụ</div>
      <?php foreach ($services as $catName => $svcs): ?>
        <div style="margin-bottom:16px">
          <h4 style="color:var(--primary);margin-bottom:10px;font-size:0.9rem;text-transform:uppercase;letter-spacing:.5px"><?= e($catName) ?></h4>
          <?php foreach ($svcs as $svc): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px dashed var(--border)">
              <div>
                <span style="font-weight:500"><?= e($svc['name']) ?></span>
                <span style="color:var(--text-light);font-size:0.85rem;margin-left:8px">⏱ <?= formatDuration($svc['duration_minutes']) ?></span>
              </div>
              <strong style="color:var(--primary)"><?= formatMoney($svc['price']) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Đánh giá -->
    <?php if (!empty($reviews)): ?>
    <div class="card">
      <div class="card-header">⭐ Đánh giá từ khách hàng</div>
      <?php foreach ($reviews as $rv): ?>
        <div style="padding:16px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <div style="width:36px;height:36px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">
              <?= $rv['is_anonymous'] ? '?' : strtoupper(mb_substr($rv['customer_name'] ?? 'K', 0, 1)) ?>
            </div>
            <div>
              <strong><?= $rv['is_anonymous'] ? 'Khách ẩn danh' : e($rv['customer_name'] ?? 'Khách hàng') ?></strong>
              <div style="font-size:0.82rem;color:var(--text-light)"><?= formatDate($rv['created_at']) ?></div>
            </div>
            <div style="margin-left:auto"><?= renderStars($rv['overall_rating']) ?></div>
          </div>
          <?php if ($rv['comment']): ?>
            <p style="color:var(--text);margin:0"><?= e($rv['comment']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /cột trái -->

  <!-- === CỘT PHẢI: đặt lịch nhanh === -->
  <div style="flex:1;min-width:280px;position:sticky;top:80px">
    <div class="card">
      <div class="card-header">📅 Đặt lịch nhanh</div>
      <?php if ($branches): ?>
        <p style="color:var(--text-light);font-size:0.9rem;margin-bottom:16px">Chọn chi nhánh và nhấn đặt lịch để tiếp tục</p>
        <?php foreach ($branches as $br): ?>
          <a href="/book?branch_id=<?= $br['id'] ?>" class="btn btn-primary" style="display:block;text-align:center;margin-bottom:10px">
            📍 <?= e($br['name']) ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:var(--text-light);text-align:center;padding:16px">Salon chưa có chi nhánh hoạt động</p>
      <?php endif; ?>
    </div>
  </div><!-- /cột phải -->

</div>
