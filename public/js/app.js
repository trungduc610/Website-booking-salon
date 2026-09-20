// public/js/app.js
// JavaScript tối giản cho GlowBook

'use strict';

// ---- Xác nhận trước khi xóa / hủy -------------------------
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Bạn có chắc chắn không?')) {
      e.preventDefault();
    }
  });
});

// ---- Auto-hide flash message sau 5 giây ------------------
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity 0.5s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

// ---- Preview ảnh trước khi upload -------------------------
document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
  input.addEventListener('change', function () {
    const previewId = this.dataset.preview;
    const preview   = document.getElementById(previewId);
    if (preview && this.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(this.files[0]);
    }
  });
});

// ---- Toggle sidebar trên mobile ---------------------------
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebar       = document.querySelector('.sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });
}

// ---- Active sidebar link -----------------------------------
const currentPath = window.location.pathname;
document.querySelectorAll('.sidebar a').forEach(link => {
  if (link.getAttribute('href') === currentPath) {
    link.classList.add('active');
  }
});
