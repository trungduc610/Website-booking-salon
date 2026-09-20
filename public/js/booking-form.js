/* =============================================================
   public/js/booking-form.js  — FILE MỚI
   Nạp ở cuối app/views/public/booking_form.php:
     <script src="<?= url('js/booking-form.js') ?>"></script>

   Vá các lỗi của form đặt lịch bản cũ:
     1. #total-display được render 1 lần rồi đứng yên — tick thêm dịch vụ
        không làm giá thay đổi, khách bấm "Xác nhận" mà không biết trả bao nhiêu.
     2. onclick="this.style.borderColor = ...checked ? 'var(--border)' :
        'var(--primary)'" bị NGƯỢC logic: sự kiện click chạy SAU khi checkbox
        đã đổi trạng thái, nên chọn dịch vụ thì viền TẮT, bỏ chọn thì viền SÁNG.
     3. Không kiểm tra khung giờ còn trống trước khi submit.
     4. Không chặn double-submit → bấm 2 lần tạo 2 lịch hẹn trùng.
   ============================================================= */
'use strict';

(function () {
  const form = document.getElementById('booking-form');
  if (!form) return;

  const totalEl  = document.getElementById('total-display');
  const durEl    = document.getElementById('duration-display');
  const endEl    = document.getElementById('endtime-display');
  const dateEl   = form.querySelector('[name="appointment_date"]');
  const timeEl   = form.querySelector('[name="appointment_start_time"]');
  const availEl  = document.getElementById('availability-note');
  const submitEl = form.querySelector('button[type="submit"]');

  const vnd = new Intl.NumberFormat('vi-VN', {
    style: 'currency', currency: 'VND', maximumFractionDigits: 0
  });

  function selectedServices() {
    return Array.from(form.querySelectorAll('input[name="service_ids[]"]:checked'));
  }

  function formatDuration(min) {
    if (min < 60) return min + ' phút';
    const h = Math.floor(min / 60), m = min % 60;
    return m ? `${h} giờ ${m} phút` : `${h} giờ`;
  }

  /* ---- 1. Cập nhật tổng tiền / thời lượng / giờ kết thúc ---- */
  function recalc() {
    let price = 0, minutes = 0;
    selectedServices().forEach(cb => {
      price   += parseFloat(cb.dataset.price || '0');
      minutes += parseInt(cb.dataset.duration || '0', 10);
    });

    if (totalEl) totalEl.textContent = vnd.format(price);
    if (durEl)   durEl.textContent   = minutes ? formatDuration(minutes) : '—';

    // Giờ kết thúc dự kiến + cảnh báo tràn sang ngày hôm sau
    if (endEl && timeEl && timeEl.value && minutes) {
      const [h, m] = timeEl.value.split(':').map(Number);
      const endMin = h * 60 + m + minutes;
      if (endMin > 24 * 60) {
        endEl.textContent = 'vượt quá 24:00';
        endEl.classList.add('text-danger');
      } else {
        endEl.textContent = String(Math.floor(endMin / 60)).padStart(2, '0') + ':' +
                            String(endMin % 60).padStart(2, '0');
        endEl.classList.remove('text-danger');
      }
    }

    if (submitEl) submitEl.disabled = selectedServices().length === 0;
    checkAvailability();
  }

  /* ---- 2. Phản hồi hình ảnh khi chọn (thay onclick sai logic) ---- */
  function syncSelectionStyles() {
    form.querySelectorAll('.service-option, .staff-option').forEach(label => {
      const input = label.querySelector('input');
      label.classList.toggle('is-selected', !!(input && input.checked));
    });
  }

  form.addEventListener('change', e => {
    if (e.target.matches('input[name="service_ids[]"], input[name="staff_id"], [name="appointment_date"], [name="appointment_start_time"]')) {
      syncSelectionStyles();
      recalc();
    }
  });

  /* ---- 3. Hỏi server khung giờ còn trống không -------------- */
  let availTimer = null;
  function checkAvailability() {
    if (!availEl || !dateEl || !timeEl) return;
    const ids = selectedServices().map(cb => cb.value);
    if (!ids.length || !dateEl.value || !timeEl.value) {
      availEl.textContent = '';
      return;
    }

    clearTimeout(availTimer);
    availTimer = setTimeout(async () => {
      availEl.textContent = 'Đang kiểm tra khung giờ…';
      availEl.className = 'form-hint';

      const params = new URLSearchParams({
        branch_id: form.querySelector('[name="branch_id"]').value,
        date: dateEl.value,
        start: timeEl.value,
        staff_id: (form.querySelector('[name="staff_id"]:checked') || {}).value || '0'
      });
      ids.forEach(id => params.append('service_ids[]', id));

      try {
        const res  = await fetch(`${window.APP_URL || ''}/api/availability?` + params.toString(), {
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.available) {
          availEl.textContent = '✅ Khung giờ này còn trống.';
          availEl.className = 'form-hint text-success';
          if (submitEl) submitEl.disabled = false;
        } else {
          availEl.textContent = '⚠️ ' + (data.message || 'Khung giờ này đã kín. Vui lòng chọn giờ khác.');
          availEl.className = 'form-error';
          if (submitEl) submitEl.disabled = true;
        }
      } catch (err) {
        // Mạng lỗi → không chặn khách; server vẫn kiểm tra lại lần cuối khi submit
        availEl.textContent = '';
      }
    }, 300);
  }

  /* ---- 4. Chặn double-submit -------------------------------- */
  let submitting = false;
  form.addEventListener('submit', e => {
    if (submitting) { e.preventDefault(); return; }
    if (selectedServices().length === 0) {
      e.preventDefault();
      alert('Vui lòng chọn ít nhất một dịch vụ.');
      return;
    }
    submitting = true;
    if (submitEl) {
      submitEl.disabled = true;
      submitEl.dataset.label = submitEl.textContent;
      submitEl.textContent = 'Đang xử lý…';
    }
    // Nếu server trả lỗi và người dùng bấm Back, mở khoá lại nút
    setTimeout(() => {
      submitting = false;
      if (submitEl) {
        submitEl.disabled = false;
        submitEl.textContent = submitEl.dataset.label || 'Xác nhận đặt lịch';
      }
    }, 10000);
  });

  syncSelectionStyles();
  recalc();
})();
