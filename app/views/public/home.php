<!-- app/views/public/home.php — Trang chủ -->

<!-- HERO -->
<div class="hero">
  <h1>💅 Đặt lịch làm đẹp dễ dàng</h1>
  <p>Hàng trăm salon uy tín trên toàn quốc — đặt lịch online trong 30 giây</p>

  <form class="hero-search" action="/search" method="GET">
    <input type="text" name="q" class="form-control" placeholder="🔍 Tìm salon, dịch vụ...">
    <select name="province_id" class="form-control" style="max-width:180px">
      <option value="">Tất cả tỉnh/thành</option>
      <?php foreach ($provinces as $p): ?>
        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Tìm ngay</button>
  </form>
</div>

<!-- TIÊU CHÍ CHỌN -->
<div class="grid-3" style="margin-bottom:48px;text-align:center">
  <div class="card" style="border-top:4px solid var(--primary)">
    <div style="font-size:2.5rem;margin-bottom:12px">⭐</div>
    <h3>Salon chất lượng</h3>
    <p style="color:var(--text-light)">Tất cả salon đều được xét duyệt kỹ trước khi lên nền tảng</p>
  </div>
  <div class="card" style="border-top:4px solid var(--secondary)">
    <div style="font-size:2.5rem;margin-bottom:12px">⚡</div>
    <h3>Đặt lịch nhanh</h3>
    <p style="color:var(--text-light)">Chọn dịch vụ, chọn giờ, xác nhận — chỉ mất 30 giây</p>
  </div>
  <div class="card" style="border-top:4px solid var(--success)">
    <div style="font-size:2.5rem;margin-bottom:12px">🔒</div>
    <h3>Thanh toán an toàn</h3>
    <p style="color:var(--text-light)">Hỗ trợ MoMo, VNPay, ZaloPay và thanh toán tại quầy</p>
  </div>
</div>

<!-- SALON NỔI BẬT -->
<div style="margin-bottom:48px">
  <div class="page-header">
    <h2>🔥 Salon nổi bật</h2>
    <a href="/search" class="btn btn-outline btn-sm">Xem tất cả →</a>
  </div>

  <?php if (empty($featured)): ?>
    <div class="card" style="text-align:center;padding:48px;color:var(--text-light)">
      <p style="font-size:2rem">🏪</p>
      <p>Chưa có salon nào. Hãy quay lại sau!</p>
    </div>
  <?php else: ?>
    <div class="grid-4">
      <?php foreach ($featured as $salon): ?>
        <a href="/salon/<?= e($salon['slug']) ?>" class="salon-card" style="display:block;text-decoration:none;color:inherit">
          <?php if ($salon['logo']): ?>
            <img src="<?= uploadUrl($salon['logo']) ?>" alt="<?= e($salon['name']) ?>">
          <?php else: ?>
            <div style="height:180px;background:linear-gradient(135deg,#fce4f3,#e8f4fd);display:flex;align-items:center;justify-content:center;font-size:3rem">💅</div>
          <?php endif; ?>
          <div class="salon-card-body">
            <div class="salon-card-name"><?= e($salon['name']) ?></div>
            <div class="salon-card-meta" style="margin:4px 0">
              <?php if ($salon['district_name']): ?>
                📍 <?= e($salon['district_name']) ?>, <?= e($salon['province_name']) ?>
              <?php endif; ?>
            </div>
            <div class="salon-card-rating">
              <?= renderStars((int)round($salon['avg_rating'])) ?>
              <span style="color:var(--text-light);font-size:0.82rem">(<?= $salon['review_count'] ?>)</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- CTA ĐĂNG KÝ SALON -->
<?php if (!Session::isLoggedIn()): ?>
<div class="card" style="background:linear-gradient(135deg,var(--primary),#c2186f);color:#fff;text-align:center;padding:48px">
  <h2 style="font-size:1.8rem;margin-bottom:12px">Bạn có salon?</h2>
  <p style="opacity:.9;margin-bottom:24px">Tham gia GlowBook để tiếp cận hàng ngàn khách hàng mới mỗi ngày</p>
  <a href="/register" class="btn btn-white btn-lg">Đăng ký miễn phí →</a>
</div>
<?php endif; ?>
