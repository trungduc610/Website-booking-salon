<?php
// ============================================================
//  app/views/public/static_page.php  — FILE MỚI
//  View dùng chung cho các trang nội dung tĩnh: /help, /contact, /privacy
//  Nhận: $heading (string), $sections (array of ['title'=>..,'body'=>..])
// ============================================================
?>

<div class="card" style="max-width:720px;margin:0 auto">
  <h1 style="margin-bottom:24px"><?= e($heading) ?></h1>

  <?php foreach ($sections as $section): ?>
    <div style="margin-bottom:24px">
      <h3 style="margin-bottom:8px"><?= e($section['title']) ?></h3>
      <p style="color:var(--text-light);line-height:1.7"><?= e($section['body']) ?></p>
    </div>
  <?php endforeach; ?>

  <a href="/" class="btn btn-outline">← Về trang chủ</a>
</div>
