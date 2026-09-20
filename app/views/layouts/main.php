<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

  <title><?= e($pageTitle ?? APP_NAME) ?><?= (isset($pageTitle) && !str_contains($pageTitle, APP_NAME)) ? ' — ' . e(APP_NAME) : '' ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Inter:wght@400;500;600&subset=vietnamese,latin&display=swap">
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Inter:wght@400;500;600&subset=vietnamese,latin&display=swap">

  <!-- ĐƯỜNG DẪN TÀI NGUYÊN TĨNH CHUẨN HOÁ BẮT ĐẦU BẰNG / -->
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="/css/fixes.css">

  <script>window.APP_URL = <?= json_encode(APP_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body>

<a href="#main" class="skip-link" style="position:absolute;left:-9999px">Bỏ qua, đến nội dung chính</a>

<header class="site-header">
  <div class="container header-inner">

    <a href="/" class="logo">💅 <?= e(APP_NAME) ?></a>

    <nav class="main-nav" aria-label="Điều hướng chính">
      <a href="/search">Tìm salon</a>

      <?php if (Session::isLoggedIn()): ?>
        <?php $role = Session::userRole(); ?>

        <?php if ($role === 'CUSTOMER'): ?>
          <a href="/customer/bookings">Lịch của tôi</a>
          <a href="/customer/vouchers">Voucher</a>
        <?php endif; ?>

        <?php if (in_array($role, ['BUSINESS_OWNER','BRANCH_MANAGER','RECEPTIONIST','STAFF'], true)): ?>
          <a href="/salon/dashboard">Quản lý salon</a>
        <?php endif; ?>

        <?php if (in_array($role, ['PLATFORM_ADMIN','COMPLIANCE','SUPPORT'], true)): ?>
          <a href="/admin/dashboard">Admin</a>
        <?php endif; ?>

        <div class="nav-user">
          <button type="button" class="nav-user-trigger" aria-haspopup="true" aria-expanded="false"
                  style="background:none;border:0;cursor:pointer;font:inherit;color:inherit;padding:8px 12px">
            👤 <?= e(Session::get('user_name')) ?>
          </button>
          <div class="dropdown" role="menu">
            <a role="menuitem" href="/customer/profile">Hồ sơ</a>
            <a role="menuitem" href="/logout">Đăng xuất</a>
          </div>
        </div>

      <?php else: ?>
        <a href="/login" class="btn-nav">Đăng nhập</a>
        <a href="/register" class="btn-nav btn-primary">Đăng ký</a>
      <?php endif; ?>
    </nav>

  </div>
</header>

<main class="main-content" id="main">
  <div class="container">
    <?php showFlash(); ?>
    <?= $content ?>
  </div>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <strong>💅 <?= e(APP_NAME) ?></strong>
        <p>Nền tảng đặt lịch làm đẹp tại Việt Nam</p>
      </div>
      <div>
        <strong>Khám phá</strong>
        <ul>
          <li><a href="/search">Tìm salon</a></li>
          <li><a href="/register">Đăng ký salon</a></li>
        </ul>
      </div>
      <div>
        <strong>Hỗ trợ</strong>
        <ul>
          <li><a href="/help">Trung tâm trợ giúp</a></li>
          <li><a href="/contact">Liên hệ</a></li>
          <li><a href="/privacy">Chính sách bảo mật</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Bảo lưu mọi quyền.</p>
    </div>
  </div>
</footer>

<script src="/js/app.js"></script>
</body>
</html>
