<!-- app/views/salon/attendance.php — Chấm công nhân viên -->
<div class="page-header">
  <div>
    <h1>⏱️ Chấm công nhân viên</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Bộ lọc ngày -->
    <div class="card" style="margin-bottom:20px">
      <form method="GET" action="/salon/attendance" style="display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="margin:0;min-width:200px">
          <label class="form-label">Chọn ngày chấm công</label>
          <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Xem</button>
      </form>
    </div>

    <!-- Bảng chấm công -->
    <div class="card">
      <div class="card-header">Bảng chấm công ngày <?= formatDateOnly($date) ?></div>

      <?php if (empty($records)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <div style="font-size:2.5rem;margin-bottom:8px">📋</div>
          <p>Chưa có dữ liệu chấm công cho ngày này.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nhân viên</th>
                <th>Vị trí</th>
                <th>Ca làm việc</th>
                <th>Giờ vào</th>
                <th>Giờ ra</th>
                <th>Đi trễ (phút)</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><strong><?= e($r['staff_name']) ?></strong></td>
                  <td><?= e($r['position'] ?? '—') ?></td>
                  <td><?= substr($r['scheduled_start_time'], 0, 5) ?> - <?= substr($r['scheduled_end_time'], 0, 5) ?></td>
                  <td><?= $r['check_in_at'] ? formatDate($r['check_in_at'], 'H:i') : '—' ?></td>
                  <td><?= $r['check_out_at'] ? formatDate($r['check_out_at'], 'H:i') : '—' ?></td>
                  <td><?= $r['late_minutes'] > 0 ? '<span style="color:var(--danger)">+' . $r['late_minutes'] . 'p</span>' : '0' ?></td>
                  <td>
                    <?php
                      $stMap = [
                        'CHECKED_IN'      => '<span class="badge badge-info">Đang làm việc</span>',
                        'CHECKED_OUT'     => '<span class="badge badge-success">Đã hoàn thành</span>',
                        'NOT_CHECKED_IN'  => '<span class="badge badge-warning">Chưa điểm danh</span>',
                        'ABSENT'          => '<span class="badge badge-danger">Vắng mặt</span>',
                        'ON_LEAVE'        => '<span class="badge badge-gray">Nghỉ phép</span>',
                      ];
                      echo $stMap[$r['status']] ?? $r['status'];
                    ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
