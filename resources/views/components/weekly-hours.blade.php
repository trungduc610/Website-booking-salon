@props(['rows', 'branchHours' => false])
@php
    $byDay = $rows->keyBy('day_of_week');
    $days = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
@endphp
<div class="table-responsive"><table class="table"><caption>Lịch theo tuần · Giờ địa phương của chi nhánh. Chưa hỗ trợ ca qua nửa đêm.</caption>
<thead><tr><th>Ngày</th><th>Bắt đầu</th><th>Kết thúc</th><th>Làm việc</th></tr></thead>
<tbody>@foreach($days as $day => $label)
@php($row = $byDay->get($day))
<tr><th scope="row">{{ $label }}<input type="hidden" name="hours[{{ $day }}][day_of_week]" value="{{ $day }}"></th>
<td><input class="form-control" aria-label="Bắt đầu {{ $label }}" type="time" required name="hours[{{ $day }}][start_time]" value="{{ old('hours.'.$day.'.start_time', substr(($branchHours ? $row?->open_time : $row?->start_time) ?? '08:00', 0, 5)) }}"></td>
<td><input class="form-control" aria-label="Kết thúc {{ $label }}" type="time" required name="hours[{{ $day }}][end_time]" value="{{ old('hours.'.$day.'.end_time', substr(($branchHours ? $row?->close_time : $row?->end_time) ?? '18:00', 0, 5)) }}"></td>
<td><select class="form-control" aria-label="Làm việc {{ $label }}" name="hours[{{ $day }}][is_off]">
@php($off = old('hours.'.$day.'.is_off', ($branchHours ? $row?->is_closed : $row?->is_off) ?? 1))
<option value="1" @selected($off == 1)>Nghỉ</option><option value="0" @selected($off == 0)>Làm việc</option></select></td>
</tr>@endforeach</tbody></table></div>
