<!-- app/views/public/search.php — Trang tìm kiếm salon -->

<div class="page-header">
  <h1>🔍 Tìm salon</h1>
  <span style="color:var(--text-light)"><?= $result['total'] ?> kết quả</span>
</div>

<!-- Bộ lọc -->
<div class="card" style="margin-bottom:24px">
  <form action="/search" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="flex:2;min-width:200px;margin:0">
      <label class="form-label">Từ khoá</label>
      <input type="text" name="q" class="form-control" placeholder="Tên salon, dịch vụ..." value="<?= e($keyword) ?>">
    </div>
    <div class="form-group" style="flex:1;min-width:160px;margin:0">
      <label class="form-label">Tỉnh/Thành</label>
      <select name="province_id" class="form-control">
        <option value="">Tất cả</option>
        <?php foreach ($provinces as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $p['id'] == $provinceId ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary">Tìm kiếm</button>
      <a href="/search" class="btn btn-white">Xoá lọc</a>
    </div>
  </form>
</div>

<!-- Kết quả -->
<?php if (empty($result['data'])): ?>
  <div class="card" style="text-align:center;padding:60px">
    <p style="font-size:3rem">😔</p>
    <h3>Không tìm thấy salon nào</h3>
    <p style="color:var(--text-light)">Hãy thử từ khoá khác hoặc bỏ bộ lọc tỉnh thành</p>
    <a href="/search" class="btn btn-outline" style="margin-top:16px">Xem tất cả salon</a>
  </div>
<?php else: ?>
  <div class="grid-3">
    <?php foreach ($result['data'] as $salon): ?>
      <a href="/salon/<?= e($salon['slug']) ?>" class="salon-card" style="display:block;text-decoration:none;color:inherit">
        <?php if ($salon['logo']): ?>
          <img src="<?= uploadUrl($salon['logo']) ?>" alt="<?= e($salon['name']) ?>">
        <?php else: ?>
          <div style="height:180px;background:linear-gradient(135deg,#fce4f3,#e8f4fd);display:flex;align-items:center;justify-content:center;font-size:3rem">💅</div>
        <?php endif; ?>
        <div class="salon-card-body">
          <div class="salon-card-name"><?= e($salon['name']) ?></div>
          <?php if ($salon['district_name']): ?>
            <div class="salon-card-meta" style="margin:4px 0">📍 <?= e($salon['district_name']) ?>, <?= e($salon['province_name']) ?></div>
          <?php endif; ?>
          <div class="salon-card-rating">
            <?= renderStars((int)round($salon['avg_rating'])) ?>
            <span style="color:var(--text-light);font-size:0.82rem">(<?= $salon['review_count'] ?> đánh giá)</span>
          </div>
          <div style="margin-top:10px">
            <span class="btn btn-primary btn-sm">Đặt lịch ngay</span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Phân trang -->
  <?php $baseUrl = '/search?q=' . urlencode($keyword) . '&province_id=' . $provinceId; ?>
  <?= renderPagination($result, $baseUrl) ?>
<?php endif; ?>
